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
use \App\Controllers\Config\Cron;
use \Luminova\Exceptions\AppException;
use \Luminova\Time\Time;
use \ReflectionMethod;
use \ReflectionFunction;
use \ReflectionNamedType;
use \ReflectionUnionType;
use \ReflectionException;
use \ReflectionIntersectionType;
use \ReflectionClass;
use \Closure;
use \DateInterval;
use \Exception;

class CronJobs extends BaseConsole 
{
    /**
     * @var int $offset port offset
    */
    private int $offset = 0;

    /**
     * @var int $tries number of tries
    */
    private int $tries = 10;

    /**
     * @var string $group command group
    */
    protected string $group = 'Cron';

    /**
     * @var string $name command name
    */
    protected string $name = 'cronjob';

    /**
     * Options
     *
     * @var array<string, string> $options
    */
    protected array $options = [
        '--force'  => 'Force update cron schedlue jobs.',
    ];

     /**
     * Usages
     *
     * @var array<string, string> $usages
     */
    protected array $usages = [
        'php novakit cron:create',
        'php novakit cron:run',
        'php novakit cron:create --force',
    ];

    /**
     * @param array $params terminal options
     * 
     * @return int 
    */
    public function run(?array $params = []): int
    {
        $this->explain($params);
        $command = trim($this->getCommand());
        $force = $this->getOption('force', false);

        $runCommand = match($command){
            'cron:create'   => $this->createCommands($force),
            'cron:run'      => $this->runCommands(),
            default         => 'unknown'
        };

        if ($runCommand === 'unknown') {
            return $this->oops($command);
        } 
            
        return (int) $runCommand;

        return STATUS_SUCCESS;
    }

    /**
     * Run helper command.
     * 
     * @param array $helps Help information.
     * 
     * @return int status code.
    */
    public function help(array $helps): int
    {
        return STATUS_ERROR;
    }

    /**
     * Executed cron jobs.
     * 
     * @return int Return status code.
    */
    private function runCommands(): int 
    {
        $cron = new Cron();
        $cron->create();
        $instance = $cron->getTask();

        if($instance === []){
            return STATUS_SUCCESS;
        }

        $network = new Network();
        $tasks = $cron->getTaskFromFile();
        $executed = 0;
        $id = 0;
        $newTasks = [];

        foreach($tasks as $id => $task) {
            $lastExecution = $task['lastExecutionDate'] ?? false;
    
            if ($lastExecution) {
                $retry = !$task['lastRunCompleted'] ?? true;
                $format = $task['interval']['format'] ?? '';
                $timezone = $task['interval']['timezone'] ?? null;
                $shouldRun = false;

                if(str_contains($format, ' ')){
                    $nextExecution = (new Time("@" . $lastExecution, $timezone))->modify($format)->setTime(0, 0);

                    exit(var_export($nextExecution));
                    
                    $now = Time::now($timezone)->setTime(0, 0);
                    $shouldRun = $now == $nextExecution;
                }else{
                    $interval = new DateInterval($format);
                    $nextExecution = Time::now($timezone)->modify("@" . $lastExecution)->add($interval);
        
                    $now = Time::now($timezone)->add($interval);
                    $shouldRun = $now >= $nextExecution;
                }

                if ($retry || $shouldRun) {
                    ob_start();
                    echo "--------------------------------------------------\n";
                    echo "| " . str_pad($task['controller'], 48, " ", STR_PAD_BOTH) . " |\n";
                    echo "| " . str_pad(($task['description'] ?? 'Cron Execution'), 48, " ", STR_PAD_BOTH) . " |\n";
                    echo "| " . str_pad("Current Time: " . Time::now()->format('Y-m-d H:i:s'), 48, " ", STR_PAD_BOTH) . " |\n";
                    echo "--------------------------------------------------\n";    
        
                    $execute = $this->callCommandMethod($task);
                    $task['lastExecutionDate'] = $now->getTimestamp();
                    $executed++;

                    if($retry){
                        $task['retries'] += 1;
                        echo "Retrying execution...\n";
                    }
    
                    if($execute){
                        if($retry){
                            echo "Retry attempt succeeded\n";
                        } else {
                            echo "Task executed successfully\n";
                        }
                        
                        $task['lastRunCompleted'] = true;
                        $task['completed'] += 1;
    
                        if(isset($instance[$id])){
                            $this->callCallbacks($network, $task, $instance, true);
                        }
                    } else {
                        if($retry){
                            echo "Retry attempt failed\n";
                        } else {
                            echo "Task execution failed, will retry again\n";
                        }
                        
                        $task['lastRunCompleted'] = false;
                        $task['failures'] += 1;
    
                        if(isset($instance[$id])){
                            $this->callCallbacks($network, $task, $instance, false);
                        }
                    }

                    if($task['log'] !== null){
                        $output = ob_get_clean(); 
                        if($output !== false || $output !== ''){  
                            make_dir($task['log']);
                            write_content($task['log'], $output . PHP_EOL, LOCK_EX);
                        }
                    }
                }
            }
    
            $newTasks[$id] = $task;
        }
    
        if($executed > 0){
            $cron->update($newTasks);
        }
    
        return STATUS_SUCCESS;
    }

    /**
     * Executed cron jobs.
     * 
     * @param Network $network Network request object.
     * @param array $task Cron task array information.
     * @param array $instance Cron task array information from class.
     * @param bool $isComplete Weatjher task is completed or not.
    */
    private function callCallbacks(Network $network, array $task, array $instance, $isComplete = true): void
    {
        $event = $isComplete ? 'Complete' : 'Failure';
        if($task['onComplete'] && isset($instance['on' . $event]) && $instance['on' . $event] instanceof Closure){
            $instance['onComplete']($task);
        }

        if($task['pingOn' . $event] && isset($instance['pingOn' . $event])){
            try{
                $network->get($instance['pingOn' . $event], $task);
                echo $event ? "Failure ping succeeded\n" : "Completed ping succeeded\n";
            } catch(Exception|AppException $e){
                echo $event ? "Failure ping failed: " : "Completed ping failed: ";
                echo $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Executed cron jobs class method.
     * 
     * @param array $task Cron task array information.
     * 
     * @return array Return true on success, false on failure.
    */
    private function callCommandMethod(array $task): bool
    {
        [$namespace, $method] = explode('::', $task['controller']);

        $reflector = new ReflectionClass($namespace);
        $instance = $reflector->newInstance();

        if ($reflector->isSubclassOf(BaseCommand::class)) {
            if ($reflector->hasMethod($method)) {
                $caller = $reflector->getMethod($method);
                if($caller->isPublic() && !$caller->isAbstract() && !$caller->isStatic()){
                    $response = $caller->invoke($instance);
                    echo "Job was executed with response: " . var_export($response, true) . "\n";
                    return true;
                }else{
                    echo "Unable to call method {$method}.\n";
                }
            }else{
                echo "Method {$method} does not exist in class {$namespace}.\n";
            }
        }else{
            echo "Class {$namespace} is not a subclass of " . BaseCommand::class . ".\n";
        }

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
                $this->writeln('Cron services has been created successfully.', 'green');
            }else{
                $this->writeln('Failed to create cron services.', 'red');
            }
        }

        if($created){
            return STATUS_SUCCESS;
        }

        return STATUS_ERROR;
    }
}