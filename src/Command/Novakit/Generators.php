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
use \App\Controllers\Config\Template;
use \Exception;

class Generators extends BaseConsole 
{
   
    /**
     * @var string $group command group
     */
    protected string $group = 'Generators';

    /**
     * @var string $name command name
     */
    protected string $name = 'create:*';

    /**
     * Options
     *
     * @var array<string, string>
     */
    protected array $options = [
        '--extend'    => 'Extend class name',
        '--implement' => 'Implement class interface',
        '--type'      => 'Type of controller',
        '--dir'      => 'Sub directory location',
    ];

    /**
     * Usages
     *
     * @var array<string, string>
     */
    protected array $usages = [
        'php novakit create:controller "name" -extend "className" -type "view"',
        'php novakit create:controller "FooController" -type "command"',
        'php novakit create:class "name" -extend "className" -implement "myInterface"',
        'php novakit create:view "name"',
        'php novakit create:class "name"',
    ];
   
    /**
     * Description
     *
     * @var string
     */
    protected string $description = 'Create controller, view or class';

    /**
     * Run the generator command.
     *
     * @param array|null $options Terminal options
     * 
     * @return int Status code
     */
    public function run(?array $options = []): int
    {
        $this->explain($options);

        $command = trim($this->getCommand());
        $name = $this->getArgument(1);

        if(empty($name)){
            $this->error('Generator name is required');
            $this->beeps();

            return STATUS_ERROR;
        }

        $type = strtolower($this->getOption('type', 'view'));
        $extend = $this->getOption('extend', null);
        $implement = $this->getOption('implement', null);
        $dir = $this->getOption('dir', '');

        $runCommand = match($command){
            'create:controller' => $this->createController($name, $type, $dir),
            'create:view'       => $this->createView($name, $dir),
            'create:class'      => $this->createClass($name, $extend, $implement),
            default             => 'unknown'
        };

        if ($runCommand === 'unknown') {
            $this->error('Unknown command ' . $this->color("'$command'", 'red') . ' not found', null);
            return STATUS_ERROR;
        } 
            
        return (int) $runCommand;
    }

    public function help(array $helps): int
    {
        return STATUS_ERROR;
    }

    /**
     * Create a controller.
     *
     * @param string      $name    Controller name
     * @param string      $tyoe  The type of controller
     * @param string|null $dir     Directory path
     * 
     * @return void
     */
    private function createController(string $name, string $tyoe = 'view', ?string $dir = null): void 
    {
        if($tyoe === 'view'){
            $use = 'use \Luminova\Base\BaseViewController;';
            $extend = 'BaseViewController';
        }elseif($tyoe === 'command'){
            $use = 'use \Luminova\Base\BaseCommand;';
            $extend = 'BaseCommand';
        }else{
            $use = 'use \Luminova\Base\BaseController;';
            $extend = 'BaseController';
        }

        $name = ucfirst($name);
        if($tyoe === 'view' || $tyoe === 'api'){
            $classContent = <<<PHP
            <?php
            namespace App\Controllers;
            
            $use
            
            class $name extends $extend
            {
                public function __construct()
                {
                    // Constructor logic goes here
                }

            PHP;

            if($tyoe === 'view'){
                $view = strtolower($name);
                $view = str_replace('controller', '', $view);
                $classContent .= <<<PHP
                    // Class methods goes here
                    public function main(): int
                    {
                        return \$this->view('$view', [

                        ]);
                    }

                PHP;
            }
        }elseif($tyoe === 'command'){
            $classContent = <<<PHP
            <?php
            namespace App\Controllers;
            
            $use
            
            class $name extends $extend
            {
                /**
                 * Override the default help implementation
                 *
                 * @param array \$helps Helps information
                 * 
                 * @return int return STATUS_SUCCESS if you implemented your own help else return STATUS_ERROR.
                */
                public function help(array \$helps): int
                {
                    // Your help logic goes here
                    // return STATUS_SUCCESS;

                    return STATUS_ERROR;
                }

                public function runTest(): int
                {
                    \$this->header();

                    return STATUS_SUCCESS;
                }

            PHP;
        }else{
            $this->error("Invalid controller --type flag: {$tyoe}, use 'api, view or command'");
            return;
        }

        $classContent .='}';

        $path = "/app/Controllers/";
        
        if($this->saveFile($classContent, $path, "{$name}.php")){
            if($tyoe === 'view'){
                $this->createView($view, $dir);
            }
        }else{
            $this->error("Unable to create class {$name}");
        }
    }
    
     /**
     * Create a view.
     *
     * @param string      $name View name
     * @param string|null $dir  Directory path
     * 
     * @return void
     */
    private function createView(string $name, ?string $dir = null): void 
    {
        
        $name = strtolower($name);
        $type = (Template::$templateEngine === 'smarty') ? '.tpl' : '.php';
        $path = "/resources/views/";

        if ($dir !== null) {
            $path .= trim($dir, '/') . '/';
        }

        if(Template::$templateEngine === 'smarty'){
            $classContent = <<<HTML
            <!DOCTYPE html>
            <html lang="{locale}">
            <head>
                <link rel="shortcut icon" href="{asset 'images/favicon.png'}" />
                <title>{\$_title}</title>
            </head>
            <body>
                <h1>Welcome to {$name}</h1>
            </body>
            </html>
            HTML;
        }else{
            $classContent = <<<HTML
            <!DOCTYPE html>
            <html lang="<?= locale()?>">
            <head>
                <link rel="shortcut icon" href="<?= asset('images/favicon.png');?>" />
                <title><?= \$this->_title;?></title>
            </head>
            <body>
                <h1>Welcome to $name</h1>
            </body>
            </html>
            HTML;
        }

        if (!$this->saveFile($classContent, $path, $name . $type)) {
            $this->error("Unable to create view {$name}");
        }
    }

    /**
     * Create a class.
     *
     * @param string      $name      Class name
     * @param string|null $extend    Class to extend
     * @param string|null $implement Interface to implement
     * 
     * @return void
     */
    private function createClass(string $name, ?string $extend = null, ?string $implement = null): void 
    {
        $use = '';
        if ($extend) {
            $use .= "use \\$extend;\n";
        }
        if ($implement) {
            $use .= "use \\$implement;";
        }

        $extendString = $extend ? " extends $extend" : '';
        $implementString = $implement ? " implements $implement" : '';
        $name = ucfirst($name);


        $classContent = <<<PHP
        <?php
        namespace App\Controllers\Utils;

        $use

        class $name$extendString$implementString
        {
            public function __construct()
            {
                // Constructor logic goes here
            }
            // Class content goes here
        }
        PHP;

        $path = "/app/Controllers/Utils/";
        
        if (!$this->saveFile($classContent, $path, "{$name}.php")) {
            $this->error("Unable to create class {$name}");
        }
    }

    /**
     * Save file to specified path.
     *
     * @param string $content  File content
     * @param string $path     File path
     * @param string $filename File name
     * 
     * @return bool
     */
    private function saveFile(string $content, string $path, string $filename): bool 
    {
        $filepath = root(__DIR__, $path) . $filename;
        $continue = 'yes';

        if(file_exists($filepath)){
            $continue = $this->prompt('File with same name "' . $filename .'", already exsit in path: "' . $path . '", do you want to continue?', ["yes", "no"], 'required|in_array(yes,no)');
        }

        if($continue === 'yes'){
            try {
                if(write_content($filepath, $content)){
                    $this->success("Completed succefully location: {$filepath}");
                    return true;
                }
            } catch(Exception $e) {
                $this->error($e->getMessage());
            }
        }

        return false;
    }
}