<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
*/
namespace Luminova\Command\Novakit;

use \Luminova\Base\BaseConsole;
use \Luminova\Base\BaseCommand;
use \Luminova\Http\Network;
use \Luminova\Time\Time;
use \Luminova\Command\Utils\Text;
use \Psr\Http\Message\ResponseInterface;
use \App\Config\Cron;
use \ReflectionClass;
use \DateInterval;
use \Exception;

class CronJobs extends BaseConsole 
{
    /**
     * {@inheritdoc}
     */
    protected string $group = 'Cron';

    /**
     * {@inheritdoc}
     */
    protected string $name = 'cronjob';

    /**
     * {@inheritdoc}
     */
    protected array $usages = [
        'php novakit cron:create --help',
        'php novakit cron:run --help'
    ];

    /**
     * Network instance.
     * 
     * @var Network|null $network
     */
    private static ?Network $network = null;

    /**
     * Application cron instance.
     * 
     * @var Cron|null $cron
     */
    private static ?Cron $cron = null;

    /**
     * {@inheritdoc}
     */
    public function run(?array $params = null): int
    {
        $this->term->explain($params);
        setenv('throw.cli.exceptions', 'true');
        $command = trim($this->term->getCommand());
        $force = $this->term->getAnyOption('force', 'f', false);
        $sleep = (int) $this->term->getAnyOption('sleep', 's', 100000);

        $runCommand = match($command){
            'cron:create'   => $this->createCommands($force),
            'cron:run'      => $this->runCommands($sleep, $force),
            default         => 'unknown'
        };

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

    /**
     * Executed cron jobs.
     * 
     * @param bool $force Force update cron lock file.
     * 
     * @return int Return status code.
     */
    private function runCommands(int $sleep, bool $force = false): int 
    {
        ob_start();
        self::$cron ??= new Cron();
        self::$cron->create($force);
        $instance = self::$cron->getTask();

        if($instance === []){
            return STATUS_SUCCESS;
        }

        $tasks = self::$cron->getTaskFromFile();
        $executed = 0;
        $id = 0;
        $output = '';
        $newTasks = [];
        $logger = [];
        $iniBody = ob_get_clean();

        foreach($tasks as $id => $task) {
            $lastExecution = $task['lastExecutionDate'] ?? false;
    
            if ($lastExecution) {
                ob_start();
                $retry = !($task['lastRunCompleted'] ?? true);
                $format = $task['interval']['format'] ?? '';
                $timezone = $task['interval']['timezone'] ?? null;
                $shouldRun = false;

                if(str_contains($format, ' ')){
                    $now = Time::now($timezone)->setTime(0, 0);
                    $shouldRun = ($now == (new Time("@" . $lastExecution, $timezone))->modify($format)->setTime(0, 0));
                }else{
                    $interval = new DateInterval($format);
                    $now = Time::now($timezone)->add($interval);
                    $shouldRun = ($now >= Time::now($timezone)->modify("@" . $lastExecution)->add($interval));
                }

                if ($retry || $shouldRun) {
                    $output .= self::setCronOutputHead(
                        $task['controller'], 
                        $task['description'] ?? ''
                    );
                    try{
                        $execute = $this->callTaskCommandMethod($task, $output);
                        $task['lastExecutionDate'] = $now->getTimestamp();
                        $executed++;

                        if($retry){
                            $task['retries'] += 1;
                            $output .= "Retrying execution...\n";
                        }
        
                        if($execute){
                            $output .= ($retry) 
                                ? "Retry attempt succeeded\n" 
                                : "Task executed successfully\n";
                            $task['lastRunCompleted'] = true;
                            $task['completed'] += 1;
        
                            if(isset($instance[$id])){
                                $this->callTaskCallbacks($task, $instance, true, $output);
                            }
                        } else {
                            $output .= ($retry) 
                                ? "Retry attempt failed\n" 
                                : "Task execution failed, will retry again\n";
                            $task['lastRunCompleted'] = false;
                            $task['failures'] += 1;
        
                            if(isset($instance[$id])){
                                $this->callTaskCallbacks($task, $instance, false, $output);
                            }
                        }
                    }catch(Exception $e){
                        $output .= 'Exception Error: ' . $e->getMessage();
                    }

                    if($task['output'] !== null && $output !== ''){
                        $logger['outputs'][$task['output']][] = $output . PHP_EOL;
                        $output = '';
                    }
                }

                if($task['log'] !== null && ($body = ob_get_clean()) !== false){
                    if(!empty(trim($body))){
                        $logger['logs'][$task['log']][] = self::setCronOutputHead(
                            $task['controller'], 
                            $task['description'] ?? 'Cron Execution',
                            $body
                        );
                    }
                    $body = null;
                }
            }
    
            $newTasks[$id] = $task;
            usleep($sleep);
        }
    
        if($executed > 0){
            self::$cron->update($newTasks);
        }
        
        self::logCronOutputs($logger, $iniBody);
        $iniBody = null;
        return STATUS_SUCCESS;
    }

    /**
     * Executed cronjob task callbacks.
     * 
     * @param array $task Cron task array information.
     * @param array $instance Cron task array information from class.
     * @param bool $isComplete Weather task is completed or not.
     * @param string &$output Log line passed by reference.
     * 
     * @return void
     */
    private function callTaskCallbacks(
        array $task, 
        array $instance, 
        bool $isComplete = true, 
        string &$output = ''
    ): void
    {
        $event = $isComplete ? 'Complete' : 'Failure';
        if($task['onComplete'] && isset($instance['on' . $event]) && is_callable($instance['on' . $event])){
            $instance['onComplete']($task);
        }

        if($task['pingOn' . $event] && isset($instance['pingOn' . $event])){
            self::$network ??= new Network();
            $output .= ($event === 'Failure') ? "Failure ping " : "Completed ping ";
            self::$network->fetch($instance['pingOn' . $event], [
                'query' => $task
            ])->then(function(ResponseInterface $res) use(&$output){
                $output .= "succeeded: ";
                $output .=  $res->getStatusCode() . "\n";
            })->catch(function(Exception $e) use(&$output){
                $output .= "failed: ";
                $output .= $e->getMessage() . "\n";
            })->error(function(Exception $e) use(&$output){
                $output .= "failed: ";
                $output .= $e->getMessage() . "\n";
            });
        }
    }

    /**
     * Executed cron jobs class method.
     * 
     * @param array $task Cron task array information.
     * @param string &$output Log line passed by reference.
     * 
     * @return array Return true on success, false on failure.
     */
    private function callTaskCommandMethod(array $task, string &$output = ''): bool
    {
        [$namespace, $method] = explode('::', $task['controller']);
        $reflector = new ReflectionClass($namespace);

        if ($reflector->isSubclassOf(BaseCommand::class)) {
            if ($reflector->hasMethod($method)) {
                $caller = $reflector->getMethod($method);
                if($caller->isPublic() && !$caller->isAbstract() && !$caller->isStatic()){
                    $response = $caller->invoke($reflector->newInstance());
                    $output .= "Job was executed with response: " . var_export($response, true) . "\n";
                    return true;
                }

                $output .= "Unable to call method {$method}.\n";
                return false;
            }

            $output .= "Method {$method} does not exist in class {$namespace}.\n";
            return false;
        }

        $output .= "Class {$namespace} is not a subclass of " . BaseCommand::class . ".\n";
        return false;
    }

    /**
     * Create or update cron task commands.
     * 
     * @param bool $force Force update cron lock file.
     * 
     * @return int Return status code.
     */
    private function createCommands(bool $force = false): int 
    {
        $created = (new Cron())->create($force);

        if($force){
            if($created){
                $this->term->writeln('Cron services has been created successfully.', 'green');
                return STATUS_SUCCESS;
            }
            
            $this->term->writeln('Failed to create cron services.', 'red');
        }

        return $created ? STATUS_SUCCESS : STATUS_ERROR;
    }

     /**
     * Log the execution outputs and error logs.
     * 
     * @param array $outputs The execution outputs to log.
     * 
     * @return void
     */
    private static function logCronOutputs(array $logger, string|bool $iniBody = false): void 
    {
        if($iniBody !== false && !empty(trim($iniBody))){
            logger('debug', "Cron Task Initialization Error: {$iniBody}.");
        }

        foreach ($logger as $key => $list) {
            foreach ($list as $to => $contents) {
                $content = trim(implode(PHP_EOL, $contents));

                if (empty($content)) {
                    continue;
                }

                if ($key === 'outputs') {
                    make_dir(pathinfo($to)['dirname']);
                    write_content($to, $content, LOCK_EX);
                } elseif ($key === 'logs') {
                    logger($to, $content);
                }

                usleep(1000);
            }
        }
    }

    /**
     * Set the output message header.
     * 
     * @param class-string $controller The execution controller class name. 
     * @param string $description The output description.
     * 
     * @return string Return the output message header.
     */
    private static function setCronOutputHead(
        string $controller, 
        string $description, 
        ?string $body = null
    ): string 
    {
        if($body !== null){
            return sprintf(
                "Task Execution Output:\nController: %s\nDescription: %s\nContent Body: %s" . PHP_EOL,
                $controller,
                $description,
                $body
            );
        }

        $description = ($description === '') ? 'Cron Execution' : $description;
        
        return Text::card(sprintf("%s\n%s\n%s",
            $controller,
            $description,
            "Current Time: " . Time::now()->format('Y-m-d H:i:s'),
        ), 2) . PHP_EOL;  
    }
}