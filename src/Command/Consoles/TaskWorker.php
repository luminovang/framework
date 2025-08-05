<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Command\Consoles;

use \Throwable;
use \Luminova\Base\Queue;
use \Luminova\Base\Console;
use \Luminova\Logger\Logger;
use \Luminova\Logger\LogLevel;
use \Luminova\Command\Utils\Text;
use \Luminova\Command\Utils\Color;
use function \Luminova\Funcs\{
    root,
    write_content,
    make_dir
};

class TaskWorker extends Console 
{
    /**
     * {@inheritdoc}
     */
    protected string $group = 'task';

    /**
     * {@inheritdoc}
     */
    protected string $name = 'TaskWorker';

    /**
     * {@inheritdoc}
     */
    protected array|string $usages = [
        'php novakit task:init --help',
        'php novakit task:deinit --help',
        'php novakit task:run --help',
        'php novakit task:listen --help',
        'php novakit task:list --help',
        'php novakit task:info --help',
        'php novakit task:queue --help',
        'php novakit task:delete --help',
        'php novakit task:purge --help',
        'php novakit task:pause --help',
        'php novakit task:status --help',
        'php novakit task:resume --help',
        'php novakit task:retry --help',
        'php novakit task:sig --help',
        'php novakit task:export --help'
    ];

    /**
     * {@inheritdoc}
     */
    public function run(?array $params = null): int
    {
        $this->term->perse($params);
        setenv('throw.cli.exceptions', 'true');

        $failed = false;
        $command = trim($this->term->getCommand() ?: '');
        ob_start();

        try {
            $runCommand = match($command){
                'task:init'       => $this->taskTable(),
                'task:deinit'     => $this->taskTable(true),
                'task:queue'      => $this->taskEnqueue(),
                'task:list'       => $this->taskList(),
                'task:info'       => $this->taskInfo(),
                'task:delete'     => $this->taskDelete(),
                'task:purge'      => $this->taskPurge(),
                'task:pause'      => $this->taskPause(),
                'task:resume'     => $this->taskResume(),
                'task:retry'      => $this->taskRetry(),
                'task:status'     => $this->taskStatus(),
                'task:run'        => $this->taskRun(),
                'task:listen'     => $this->taskListen(),
                'task:sig'        => $this->taskSignal(),
                'task:export'     => $this->taskExport(),
                default           => 'unknown'
            };
        }catch(Throwable $e){
            $runCommand = STATUS_ERROR;
            $failed = true;
            echo "Error [{$e->getCode()}]: {$e->getMessage()}\n";

            $task = $this->getTaskInstance();

            if($task instanceof Queue){
                try{
                    if(!$task->isInitialized()){
                        echo sprintf(
                            "Task queue \"%s\" does not appear to be initialized.\n" .
                            "To initialize, run:\n" .
                            "  \"php novakit task:init\"\n" .
                            "If the issue persists, try resetting:\n" .
                            "  \"php novakit task:deinit && php novakit task:init\"\n",
                            $task::class
                        );
                    }
                }catch(Throwable $err){
                    echo sprintf(
                        "Task database check failed [%d]: %s\n" . 
                        "Ensure the database connection is correctly configured and accessible.\n",
                        $err->getCode(),
                        $err->getMessage()
                    );
                }

                $task->close();
            }
        }

       $this->logOutput(trim(ob_get_clean() ?: ''), $failed);

        if ($runCommand === 'unknown') {
            return $this->term->oops($command);
        } 
            
        return (int) $runCommand;
    }

    /**
     * {@inheritdoc}
     */
    public function help(array $helps): int
    {
        return STATUS_ERROR;
    }

    private function getTaskInstance(bool $isRunner = false): ?Queue 
    {
        $class = $this->term->getAnyOption('class', 'c', '\\App\\Tasks\\TaskQueue');

        if (!str_starts_with($class, '\\App\\Tasks\\') && !class_exists($class)) {
            $class = '\\App\\Tasks\\' . ltrim($class, '\\');
        }

        if (!class_exists($class)) {
            $error = sprintf('Invalid or missing task class: [%s]. Cannot proceed.', $class);

            if($isRunner){
                echo $error ."\n";
                return null;
            }

            $this->term->error($error);

            return null;
        }

        /**
         * @var \T<Queue> $task
         */
        $task = new $class();

        if(!$task instanceof Queue){
            $error = sprintf('Class [%s] must extend %s to manage tasks.', $class, Queue::class);

            if($isRunner){
                echo $error ."\n";
                return null;
            }

            $this->term->error($error);
            
            return $task = null;
        }

        $task->mode = 'cli';
        $task->returnAsTaskModel = false;
        $task->setTerminal($this->term);

        return $task;
    }

    /**
     * Command: 
     *  - Create: php novakit task:init -c=App\\Tasks\\Test
     *  - Drop:   php novakit task:deinit -c=App\\Tasks\\Test
     */
    private function taskTable(bool $isDrop = false): int 
    {
        $task = $this->getTaskInstance();

        if(!$task instanceof Queue){
            return STATUS_ERROR;
        }

        if ($isDrop) {
            if ($task->deinit()) {
                $task->close();
                $this->term->writeln('Task table was dropped successfully.', 'green');
                return STATUS_SUCCESS;
            }

            $task->close();
            $this->term->writeln('Nothing was dropped. Task table may not exist.', 'yellow');
            return STATUS_ERROR;
        }

        if ($task->init()) {
            $task->close();
            $this->term->writeln('Task table was created successfully.', 'green');
            return STATUS_SUCCESS;
        }

        $task->close();
        $this->term->writeln('No changes made. Task table may already exist.', 'yellow');
        return STATUS_ERROR;
    }

    /**
     * Command: 
     *  - Queue: php novakit task:queue \
     *      -t=App\\Utils\\Test@method \
     *      -a='[true, 1, "foo"]'
     *      -p=1
     *      -s=now + 5
     * 
     * php novakit task:queue -t=App\Controllers\Http\HomeController::testRun -a=[30]
     */
    private function taskEnqueue(): int 
    {
        $task = $this->getTaskInstance();

        if (!$task instanceof Queue) {
            return STATUS_ERROR;
        }

        $result = 0;
        $handler = $this->term->getAnyOption('task', 't', null);
        $isBatch = false;

        if (!$handler) {
            $tasks = $task->getStagedTasks();

            if(!$tasks || $tasks === []){
                $task->close();
                $this->term->error('Missing task handler. Use -h=<Class@method>, static method -h=<Class::method> or -h=functionName.');
                return STATUS_ERROR;
            }

            $isBatch = true;
            $result = $task->batchEnqueue($tasks);
        }else{
            $priority = (int) $this->term->getAnyOption('priority', 'p', 0);
            $retries = (int) $this->term->getAnyOption('retries', 'r', 0);
            $schedule = $this->term->getAnyOption('schedule', 's', null);
            $forever = $this->term->getAnyOption('forever', 'f', null);
            $forever = ($forever === null) ? null : (int) $forever;
            $rawArgs = $this->term->getAnyOption('args', 'a', '[]');

            if($forever !== null && $forever < 5){
                $this->term->error("The --forever interval must be at least 5 minutes. Given: {$forever}");
                return STATUS_ERROR;
            }

            $arguments = [];

            if ($rawArgs !== '[]') {
                if (is_string($rawArgs)) {
                    $arguments = json_validate($rawArgs) ? json_decode($rawArgs, true) : [];
                } elseif (is_array($rawArgs)) {
                    $arguments = $rawArgs;
                }
            }

            $result = $task->enqueue(
                $handler, 
                $arguments, 
                $schedule, 
                $priority, 
                $forever,
                $retries
            );
        }

        $task->close();

        if ($result === -0) {
            $this->term->writeln("Handler [$handler] is not allowed or invalid.", 'red');
            return STATUS_ERROR;
        }

        if ($result) {
            $this->term->writeln($isBatch 
                    ? "[$result] task(s) was queued successfully."
                    : "Task was queued successfully with ID: #{$result} Handler: [$handler].", 
                'green'
            );
            return STATUS_SUCCESS;
        }

        $this->term->writeln("Task could not be queued. Check handler and arguments.", 'yellow');
        return STATUS_ERROR;
    }

    /**
     * Command:
     *  - php novakit task:list \
     *      -c=App\\Tasks\\Test \
     *      -o=0 \
     *      -l=25 \
     *      -s=pending
     */
    private function taskList(): int 
    {
        $task = $this->getTaskInstance();

        if (!$task instanceof Queue) {
            return STATUS_ERROR;
        }

        $offset = (int) $this->term->getAnyOption('offset', 'o', 0);
        $limit  = $this->term->getAnyOption('limit', 'l', null);
        $status = $this->term->getAnyOption('status', 's', Queue::ALL);

        $result = $task->list(
            $status, 
            ($limit !== null) ? (int) $limit : null, 
            $offset,
            true
        );

        if (!$result) {
            $task->close();
            $this->term->writeln('No tasks found for the given status or range.', 'yellow');
            return STATUS_ERROR;
        }

        $header = ['#', 'Handler', 'Status', 'Try', 'Prio', 'Freq', 'Created', 'Updated'];
        $rows = [];

        foreach ($result['tasks'] as $item) {
            $handler = $task->isOpisClosure($item['handler'] ?? '') 
                ? 'Opis\\Closure\\Serializer@anonymous' 
                : ($item['handler'] ?? '-'); 
            $rows[] = [
                '#'         => $item['id'] ?? '-',
                'Handler'   => $handler,
                'Status'    => $item['status'] ?? '-',
                'Try'       => $item['attempts'] ?? '0',
                'Prio'      => $item['priority'] ?? '0',
                'Freq'      => $item['forever'] ?: 'ONCE',
                'Created'   => $item['created_at'] ?? '-',
                'Updated'   => $item['updated_at'] ?? '-',
            ];
        }

        $task->close();

        $this->term->writeln("Total tasks ({$result['count']} of {$result['total']})", 'green');
        $this->term->write(
            $this->term->table($header, $rows, null, 'yellow')
        );

        return STATUS_SUCCESS;
    }

    /**
     * Command:
     *  - php novakit task:list \
     *      -c=App\\Tasks\\Test \
     *      -i=0 \
     *      -s=pending
     */
    private function taskStatus(): int 
    {

        $id = (int) $this->term->getAnyOption('id', 'i', null);
        $status = $this->term->getAnyOption('status', 's', null);

        if (!$id) {
            $this->term->error('Missing task ID. Use -i=<id> to specify the task.');
            return STATUS_ERROR;
        }

        if (!$status) {
            $this->term->error('Missing status. Use -s=<status> to provide a new status value.');
            return STATUS_ERROR;
        }

        $task = $this->getTaskInstance();

        if (!$task instanceof Queue) {
            return STATUS_ERROR;
        }

        $updated = $task->status($id, $status);
        $task->close();

        if (!$updated) {
            $this->term->writeln("Failed to update task #$id to status [$status].", 'red');
            return STATUS_ERROR;
        }

        $this->term->writeln("Task #$id status updated to [$status].", 'green');
        return STATUS_SUCCESS;
    }

    /**
     * Command:
     *  - php novakit task:info \
     *      -c=App\\Tasks\\Test \
     *      -i=34
     */
    private function taskInfo(): int
    {
        $id = (int) $this->term->getAnyOption('id', 'i', 0);

        if ($id < 1) {
            $this->term->error('Invalid or missing task ID. Use -i=<task_id>.');
            return STATUS_ERROR;
        }

        $task = $this->getTaskInstance();

        if (!$task instanceof Queue) {
            return STATUS_ERROR;
        }

        $row = $task->get($id);
        $task->close();

        if (!$row) {
            $this->term->writeln("Task with ID [$id] not found.", 'yellow');
            return STATUS_ERROR;
        }

        $rows = [];
        foreach ($row as $key => $value) {
            if($key === 'outputs'){
                continue;
            }

            $value = ($key === 'handler' && $task->isOpisClosure($value ?? ''))
                ? 'Opis\\Closure\\Serializer@anonymous' 
                : $value; 

            $rows[] = [
                'Field' => $this->getMapColumnName($key),
                'Value' => is_array($value) 
                    ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) 
                    : $value ?? '-'
            ];
        }

        $this->term->write($this->term->table(
            headers: ['Field', 'Value'], 
            rows: $rows,
            headerColor: 'green',
            retainNewlines: true
        ));

        $outputs = $row['outputs'] ?? null;

        if ($outputs) {
            $content = json_decode($outputs, true);
            $this->writeBlock('Task Execution Result', $content['response'] ?? null, 'green');
            $this->writeBlock('Task Execution Output', $content['output'] ?? null, 'yellow');
        }

        return STATUS_SUCCESS;
    }

    /**
     * Command:
     *  - php novakit task:delete \
     *      -c=App\\Tasks\\Test \
     *      -i=42
     */
    private function taskDelete(): int
    {
        $id = (int) $this->term->getAnyOption('id', 'i', 0);

        if ($id < 1) {
            $this->term->error('Invalid or missing task ID. Use -i=<task_id>.');
            return STATUS_ERROR;
        }

        $task = $this->getTaskInstance();

        if (!$task instanceof Queue) {
            return STATUS_ERROR;
        }

        if (!$task->delete($id)) {
            $task->close();
            $this->term->writeln("Task with ID [$id] could not be deleted or does not exist.", 'yellow');
            return STATUS_ERROR;
        }

        $task->close();
        $this->term->writeln("Task with ID [$id] has been deleted.", 'green');
        return STATUS_SUCCESS;
    }

    /**
     * Command:
     *  - php novakit task:purge \
     *      -c=App\\Tasks\\Test \
     *      -s=completed
     */
    private function taskPurge(): int
    {
        $task = $this->getTaskInstance();

        if (!$task instanceof Queue) {
            return STATUS_ERROR;
        }

        $status = $this->term->getAnyOption('status', 's', Queue::ALL);
        $count = $task->purge($status);

        if ($count === 0) {
            $task->close();
            $this->term->writeln("No tasks matched for purging with status [$status].", 'yellow');
            return STATUS_ERROR;
        }

        $task->close();
        $this->term->writeln("Purged $count task(s) with status [$status].", 'green');
        return STATUS_SUCCESS;
    }

    /**
     * Command:
     *  - php novakit task:pause -c=App\\Tasks\\Test -i=42
     */
    private function taskPause(): int
    {
        $id = (int) $this->term->getAnyOption('id', 'i', 0);

        if ($id < 1) {
            $this->term->error('Missing or invalid task ID.');
            return STATUS_ERROR;
        }

        $task = $this->getTaskInstance();

        if (!$task instanceof Queue) {
            return STATUS_ERROR;
        }

        if (!$task->pause($id)) {
            $task->close();
            $this->term->writeln("Task ID [$id] could not be paused or doesn't exist.", 'yellow');
            return STATUS_ERROR;
        }

        $task->close();
        $this->term->writeln("Task ID [$id] has been paused.", 'green');
        return STATUS_SUCCESS;
    }

    /**
     * Command:
     *  - php novakit task:resume -c=App\\Tasks\\Test -i=42
     */
    private function taskResume(): int
    {
        $id = (int) $this->term->getAnyOption('id', 'i', 0);

        if ($id < 1) {
            $this->term->error('Missing or invalid task ID.');
            return STATUS_ERROR;
        }

        $task = $this->getTaskInstance();

        if (!$task instanceof Queue) {
            $this->term->error('Invalid task class.');
            return STATUS_ERROR;
        }

        if (!$task->resume($id)) {
            $task->close();
            $this->term->writeln("Task ID [$id] could not be resumed or isn't paused.", 'yellow');
            return STATUS_ERROR;
        }

        $task->close();
        $this->term->writeln("Task ID [$id] has been resumed.", 'green');
        return STATUS_SUCCESS;
    }

    /**
     * Command:
     *  - php novakit task:retry -c=App\\Tasks\\Test -i=42
     */
    private function taskRetry(): int
    {
        $id = (int) $this->term->getAnyOption('id', 'i', 0);

        if ($id < 1) {
            $this->term->error('Missing or invalid task ID.');
            return STATUS_ERROR;
        }

        $task = $this->getTaskInstance();

        if (!$task instanceof Queue) {
            $this->term->error('Invalid task class.');
            return STATUS_ERROR;
        }

        if (!$task->retry($id)) {
            $task->close();
            $this->term->writeln("Task ID [$id] could not be retried.", 'yellow');
            return STATUS_ERROR;
        }

        $task->close();
        $this->term->writeln("Task ID [$id] has been marked for retry.", 'green');
        return STATUS_SUCCESS;
    }

    /**
     * Command:
     *  php novakit task:run \
     *      -c=App\\Tasks\\Test \
     *      -o=debug or -o=path/to/log.txt \
     *      -s=1000  # sleep in microseconds
     *      -l=5     # task batch limit
     *      -i=10    # max idle loops before exit
     */
    private function taskRun(): int
    {
        $task = $this->getTaskInstance(true);

        if ($task instanceof Queue) {
            $flock = $this->term->getAnyOption('flock-worker', 'f');

            if($flock){
                if($task->isLocked()){
                    return STATUS_SUCCESS;
                }

                $task->lock();
            }

            $sleep  = (int) $this->term->getAnyOption('sleep', 's', 100000);
            $limit  = $this->term->getAnyOption('limit', 'l');
            $idle   = (int) $this->term->getAnyOption('idle', 'i', 10);

            $task->onComplete = function(int $id, string $handler, string $status){
                $label = Color::style($handler, 'lightYellow');
                $spacing = Text::padding('', 10, Text::RIGHT);

                $this->term->writeln("  Task #[{$id}] {$label}{$spacing}{$status}");
            };

            $task->run($sleep, ($limit !== null) ? (int) $limit : null, $idle);

            if($flock){
               $task->unlock();
            }

            $task->close();
        }

        return STATUS_SUCCESS;
    }

    /**
     * Command:
     *  php novakit task:listen \
     *      -c=App\\Tasks\\Test \
     */
    private function taskListen(): int
    {
        $task = $this->getTaskInstance(true);

        if (!$task instanceof Queue) {
            return STATUS_ERROR;
        }

        $info = $task->getPathInfo('event');

        if (!$info) {
            $this->term->writeln(sprintf(
                'Missing logging flag. Set "%s::$eventLogging" to true, e.g., "%s::$eventLogging = true".',
                $task::class,
                $task::class
            ), 'red');
            return STATUS_ERROR;
        }

        $filename = $info[1];

        if (file_exists($filename) && !is_file($filename)) {
            $this->term->writeln("Task events log file not found: $filename", 'yellow');
            return STATUS_ERROR;
        }

        $this->term->writeln(sprintf("Listening for task events in: %s", $task::class));
        $this->term->writeln('Press Ctrl+C to stop.', 'green');
        $this->term->newLine();

        $task = null;
        $lastSize = 0;

        while (true) {
            if (!file_exists($filename)) {
                continue;
            }

            if (!is_readable($filename)) {
                $this->term->error("Cannot read tasks event log file: $filename");
                break;
            }

            clearstatcache(true, $filename);
            $currentSize = filesize($filename);

            if ($currentSize > $lastSize) {
                $fp = fopen($filename, 'r');
                if ($fp) {
                    fseek($fp, $lastSize);
                    while (!feof($fp)) {
                        echo fread($fp, 4096);
                    }

                    fclose($fp);
                    $lastSize = $currentSize;
                }
            }

            usleep(500_000);
        }

        return STATUS_SUCCESS;
    }

    /**
     * Stop Command:
     *  php novakit task:sig \
     *      -c=App\\Tasks\\Test \
     *      -s
     * 
     * Resume Command:
     *  php novakit task:sig \
     *      -c=App\\Tasks\\Test \
     *      -r
     */
    private function taskSignal(): int 
    {
        $task = $this->getTaskInstance(true);

        if (!$task instanceof Queue) {
            return STATUS_ERROR;
        }

        $stop = $this->term->getAnyOption('stop-worker', 's');
        $resume = $this->term->getAnyOption('resume-worker', 'r');

        if(!$stop && !$resume){
            $this->term->writeln("Please specify either --stop-worker (-s) or --resume-worker (-r).", 'yellow');
            return STATUS_ERROR;
        }

        if($stop && $resume){
            $this->term->writeln("Cannot use --stop-worker and --resume-worker at the same time.", 'yellow');
            return STATUS_ERROR;
        }

        [$path, $filename] = $this->getSignalPath($task);

        if ($stop) {
            if (make_dir($path) && file_put_contents($filename, '1')) {
                $this->term->writeln("Worker stop signal created: {$filename}", 'green');
                return STATUS_SUCCESS;
            }

            $this->term->writeln("Failed to create stop signal file.", 'red');
            return STATUS_ERROR;
        }

        if ($resume) {
            if (file_exists($filename) && unlink($filename)) {
                $this->term->writeln("Worker resume signal processed. Signal file removed.", 'green');
                return STATUS_SUCCESS;
            }

            $this->term->writeln("No signal file found to remove, or failed to delete.");
            return STATUS_ERROR;
        }

        return STATUS_ERROR;
    }

    private function taskExport(): int 
    {
        $task = $this->getTaskInstance(true);

        if (!$task instanceof Queue) {
            return STATUS_ERROR;
        }

        $dir = $this->term->getAnyOption('dir', 'd');

        if (!$dir) {
            $this->term->writeln("✖ No export path specified. Use --dir=path/to/file.txt", 'yellow');
            return STATUS_ERROR;
        }

        $status = $this->term->getAnyOption('status', 's', Queue::ALL);
        $metadata = '';

        if ($task->export($status, $dir, $metadata)) {
            $this->term->writeln("✔ Tasks were successfully exported to: {$dir}", 'green');

            if (!empty($metadata)) {
                $this->term->writeln($metadata);
            }

            return STATUS_SUCCESS;
        }

        $this->term->writeln("✖ Failed to export tasks to: {$dir}", 'red');
        return STATUS_ERROR;
    }

    /**
     * Get signal file path from class or create custom.
     */
    private function getSignalPath(Queue $task): array
    {
        $info = $task->getPathInfo('signal');

        if($info !== null){
            return $info;
        }

        $id = str_replace('\\', '_', $task::class);
        $path = root("/writeable/temp/worker/signal/");

        return [$path, "{$path}task_{$id}.lock"];
    }

    /**
     * Write a styled block with optional JSON formatting.
     *
     * @param string $title Block title to print (styled).
     * @param string|array|null $value The value to print.
     * @param string $color Text color for the title.
     */
    protected function writeBlock(string $title, mixed $value, string $color): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $this->term->writeln(Text::style($title, Text::FONT_BOLD | Text::FONT_UNDERLINE), $color);
        $this->term->newLine();

        $output = is_array($value) || is_object($value)
            ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            : (string) $value;

        $this->term->writeln($output);
    }

    private function getMapColumnName(string $column): string 
    {
        return match($column){
            'id'           => 'Task ID',
            'priority'     => 'Execution Priority',
            'attempts'     => 'Execution Attempts',
            'retries'      => 'Retry Limit',
            'forever'      => 'Recurring Interval (minutes)',
            'status'       => 'Task Status',
            'group_name'   => 'Task Group',
            'handler'      => 'Task Handler',
            'arguments'    => 'Handler Arguments',
            'signature'    => 'Task Signature (MD5)',
            'outputs'      => 'Execution Outputs',
            'scheduled_at' => 'Scheduled Time',
            'created_at'   => 'Creation Time',
            'updated_at'   => 'Last Updated',
            default         => ucfirst($column)
        };
    }

    private function logOutput(string $output, bool $failed): void 
    {
        if($output === ''){
            return;
        }

        $logger = $this->term->getAnyOption('output', 'o');

        if (!$logger) {
            $this->term->writeln($output, $failed ? 'red' : null);
            return;
        }

        if (LogLevel::has($logger)) {
            if($failed && !LogLevel::isCritical($logger)){
                $logger = LogLevel::CRITICAL;
            }

            Logger::dispatch($logger, $output);
            return;
        } 
        
        if(($info = pathinfo($logger)) !== []){
            make_dir($info['dirname']);
            write_content($logger, $output, LOCK_EX);
            return;
        }
    }
}