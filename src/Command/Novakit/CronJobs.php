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
use \App\Config\Cron;
use \Luminova\Exceptions\AppException;
use \ReflectionClass;
use \Closure;
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
     * @var Network|null $network network instance
    */
    private static ?Network $network = null;

    /**
     * @var Cron|null $cron Application cron instance.
    */
    private static ?Cron $cron = null;

    /**
     * {@inheritdoc}
    */
    public function run(?array $params = null): int
    {
        $this->explain($params);
        setenv('throw.cli.exceptions', 'true');
        $command = trim($this->getCommand());
        $force = $this->getAnyOption('force', 'f', false);

        $runCommand = match($command){
            'cron:create'   => $this->createCommands($force),
            'cron:run'      => $this->runCommands($force),
            default         => 'unknown'
        };

        if ($runCommand === 'unknown') {
            return $this->oops($command);
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
    private function runCommands(bool $force = false): int 
    {
        self::$cron ??= new Cron();
        self::$cron->create($force);
        $instance = self::$cron->getTask();

        if($instance === []){
            return STATUS_SUCCESS;
        }

        $tasks = self::$cron->getTaskFromFile();
        $executed = 0;
        $id = 0;
        $newTasks = [];
        $logger = '';

        foreach($tasks as $id => $task) {
            $lastExecution = $task['lastExecutionDate'] ?? false;
    
            if ($lastExecution) {
                $retry = !$task['lastRunCompleted'] ?? true;
                $format = $task['interval']['format'] ?? '';
                $timezone = $task['interval']['timezone'] ?? null;
                $shouldRun = false;

                if(str_contains($format, ' ')){
                    $nextExecution = (new Time("@" . $lastExecution, $timezone))->modify($format)->setTime(0, 0);
                    
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
                    $logger .= "--------------------------------------------------\n";
                    $logger .=  "| " . str_pad($task['controller'], 48, " ", STR_PAD_BOTH) . " |\n";
                    $logger .=  "| " . str_pad(($task['description'] ?? 'Cron Execution'), 48, " ", STR_PAD_BOTH) . " |\n";
                    $logger .=  "| " . str_pad("Current Time: " . Time::now()->format('Y-m-d H:i:s'), 48, " ", STR_PAD_BOTH) . " |\n";
                    $logger .=  "--------------------------------------------------\n";    

                    try{
                        $execute = $this->callCommandMethod($task, $logger);
                        $task['lastExecutionDate'] = $now->getTimestamp();
                        $executed++;

                        if($retry){
                            $task['retries'] += 1;
                            $logger .=  "Retrying execution...\n";
                        }
        
                        if($execute){
                            if($retry){
                                $logger .=  "Retry attempt succeeded\n";
                            } else {
                                $logger .=  "Task executed successfully\n";
                            }
                            
                            $task['lastRunCompleted'] = true;
                            $task['completed'] += 1;
        
                            if(isset($instance[$id])){
                                $this->callCallbacks($task, $instance, true, $logger);
                            }
                        } else {
                            if($retry){
                                $logger .= "Retry attempt failed\n";
                            } else {
                                $logger .= "Task execution failed, will retry again\n";
                            }
                            
                            $task['lastRunCompleted'] = false;
                            $task['failures'] += 1;
        
                            if(isset($instance[$id])){
                                $this->callCallbacks($task, $instance, false, $logger);
                            }
                        }
                    }catch(Exception $e){
                        $logger .= 'Exception Error: ' . $e->getMessage();
                    }

                    if($task['log'] !== null && $logger !== ''){
                        logger($task['log'], rtrim($logger, "\n"));
                        $logger = '';
                    }

                    if($task['output'] !== null){
                        $output = ob_get_clean(); 
                        if($output !== false || $output !== ''){  
                            make_dir(pathinfo($task['output'])['dirname']);
                            write_content($task['output'], $output . PHP_EOL, LOCK_EX);
                            $output = false;
                        }
                    }
                }
            }
    
            $newTasks[$id] = $task;
        }
    
        if($executed > 0){
            self::$cron->update($newTasks);
        }
    
        return STATUS_SUCCESS;
    }

    /**
     * Executed cron jobs.
     * 
     * @param array $task Cron task array information.
     * @param array $instance Cron task array information from class.
     * @param bool $isComplete Weather task is completed or not.
     * @param string &$logger Log line passed by reference.
    */
    private function callCallbacks(array $task, array $instance, $isComplete = true, string &$logger = ''): void
    {
        $event = $isComplete ? 'Complete' : 'Failure';
        if($task['onComplete'] && isset($instance['on' . $event]) && $instance['on' . $event] instanceof Closure){
            $instance['onComplete']($task);
        }

        if($task['pingOn' . $event] && isset($instance['pingOn' . $event])){
            self::$network ??= new Network();
            try{
                self::$network->get($instance['pingOn' . $event], [
                    'query' => $task
                ]);
                $logger .= $event ? "Failure ping succeeded\n" : "Completed ping succeeded\n";
            } catch(Exception|AppException $e){
                $logger .= $event ? "Failure ping failed: " : "Completed ping failed: ";
                $logger .= $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Executed cron jobs class method.
     * 
     * @param array $task Cron task array information.
     * @param string &$logger Log line passed by reference.
     * 
     * @return array Return true on success, false on failure.
    */
    private function callCommandMethod(array $task, string &$logger = ''): bool
    {
        [$namespace, $method] = explode('::', $task['controller']);

        $reflector = new ReflectionClass($namespace);
        $instance = $reflector->newInstance();

        if ($reflector->isSubclassOf(BaseCommand::class)) {
            if ($reflector->hasMethod($method)) {
                $caller = $reflector->getMethod($method);
                if($caller->isPublic() && !$caller->isAbstract() && !$caller->isStatic()){
                    $response = $caller->invoke($instance);
                    $logger .= "Job was executed with response: " . var_export($response, true) . "\n";
                    return true;
                }else{
                    $logger .= "Unable to call method {$method}.\n";
                }
            }else{
                $logger .= "Method {$method} does not exist in class {$namespace}.\n";
            }
        }else{
            $logger .= "Class {$namespace} is not a subclass of " . BaseCommand::class . ".\n";
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

        return $created ? STATUS_SUCCESS : STATUS_ERROR;
    }
}