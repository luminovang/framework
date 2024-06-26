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

class Context extends BaseConsole 
{
    /**
     * {@inheritdoc}
    */
    protected string $group = 'Context';

    /**
     * {@inheritdoc}
    */
    protected string $name = 'context';

    /**
     * {@inheritdoc}
    */
    protected array $options = [
        '--no-error' => 'Ignore adding error handler'
    ];

    /**
     * {@inheritdoc}
    */
    protected array $usages = [
        'php novakit context "test"',
        'php novakit context "test" --no-error'
    ];

    /**
     * {@inheritdoc}
    */
    protected string $description = 'Install router context';

    /**
     * {@inheritdoc}
    */
    public function run(?array $options = []): int
    {
        $this->explain($options);

        $command = trim($this->getCommand());
        $name = $this->getArgument(1);
        $noError = (bool) $this->getOption('no-error', false);

        if(empty($name)){
            $this->error('Context name is required');
            $this->beeps();

            return STATUS_ERROR;
        }

        $runCommand = match($command){
            'context' => $this->installContext($name, $noError),
            default => null
        };

        if ($runCommand === null) {
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
     * Install the router context.
     *
     * @param string $name Context name
     * @param bool $noError No error handler
     * 
     * @return int Status code
     */
    private function installContext(string $name, bool $noError = false): int 
    {
        $camelcase = camel_case('on' . $name) . 'Error';
        $controller = ucfirst($name) . 'Controller::index';
        $onError = ($noError ? '' : ', ' . "[ViewErrors::class, '$camelcase']");
        $index = root('public') . 'index.php';
        $indexContent = file_get_contents($index);

        $handler = <<<PHP
        <?php 
        /** @var \Luminova\Routing\Router \$router */
        /** @var \App\Controllers\Application \$app */
        
        \$router->get('/', '$controller');
        PHP;

        $newContext = <<<PHP
            new Context('$name' $onError)
        PHP;

        $postion = strpos($indexContent, 'app()->context(') + strlen('app()->context(');
        $content = substr_replace($indexContent, "\n$newContext,", $postion, 0);

        if (strpos($name, ' ') !== false) {
            $this->writeln('Your context name contains space characters', 'red');

            return STATUS_ERROR;
        }

        if (has_uppercase($name)) {
            $this->beeps();
            $input = $this->chooser('Your context name contains uppercased character, are you sure you want to continue?', ['Continue', 'Abort'], true);

            if($input == 0){
                if(write_content($index, $content)){
                    write_content(root('routes') . $name . '.php', $handler);
                    $this->writeln("Route context installed: {$name}", 'green');

                    return STATUS_SUCCESS;
                }
            }

            $this->writeln('No changes was made');
            
            return STATUS_ERROR;
        }else{
            if(write_content($index, $content)){
                write_content(root('routes') . $name . '.php', $handler);
                $this->writeln("Route context installed: {$name}", 'green');

                return STATUS_SUCCESS;
            }
        }

        $this->writeln("Unable to install router context {$name}", 'red');
        return STATUS_ERROR;
    }
}