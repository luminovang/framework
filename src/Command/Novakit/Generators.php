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
     * @var array<string|int,string> $options
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
     * @var array<string|int,string> $usages
     */
    protected array $usages = [
        'php novakit create:controller "name" -extend "className" -type "view"',
        'php novakit create:controller "FooController" -type "command"',
        'php novakit create:class "name" -extend "className" -implement "myInterface"',
        'php novakit create:view "name"',
        'php novakit create:class "name"',
        'php novakit create:model "name"',
    ];
   
    /**
     * Description
     *
     * @var string $description
     */
    protected string $description = 'Create controller, view or class';

    /**
     * @var string|null $engine
    */
    private static ?string $engine = null;

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
            $this->writeln('Generator name is required', 'red');
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
            'create:class'      => $this->createUtilClass($name, $extend, $implement),
            'create:model'      => $this->createModel($name, $implement),
            default             => 'unknown'
        };

        if ($runCommand === 'unknown') {
            return $this->oops($command);
        } 
            
        return (int) $runCommand;
    }

    /**
     * Command helper method.
     * 
     * @param array $helps Help information.
     * 
     * @return int
    */
    public function help(array $helps): int
    {
        return STATUS_ERROR;
    }

    /**
     * Create a controller.
     *
     * @param string $name  Controller name.
     * @param string $tyoe  The type of controller.
     * @param string|null $dir Directory path.
     * 
     * @return void
     */
    private function createController(string $name, string $tyoe = 'view', ?string $dir = null): void 
    {
        $view = '';
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
                    /**
                     * Constructor logic goes here.
                     * parent::__construct();
                    */
                }

            PHP;

            if($tyoe === 'view'){
                $view = strtolower($name);
                $view = str_replace('controller', '', $view);
                $classContent .= <<<PHP
                    // Class methods goes here
                    public function main(): int
                    {
                        return \$this->view('$view', []);
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
                 * Override the default help implementation.
                 *
                 * @param array \$helps Helps information.
                 * 
                 * @return int return STATUS_SUCCESS if you implemented your own help else return STATUS_ERROR.
                */
                public function help(array \$helps): int
                {
                    return STATUS_ERROR;
                }

                /**
                 * Run text command.
                 * 
                 * @return int Return status code.
                */
                public function runTest(): int
                {
                    \$this->header();
                    return STATUS_SUCCESS;
                }

            PHP;
        }else{
            $this->writeln("Invalid controller --type flag: {$tyoe}, use 'api, view or command'", 'red');
            return;
        }

        $classContent .='}';

        $path = "/app/Controllers/";
        
        if($this->saveFile($classContent, $path, "{$name}.php")){
            if($tyoe === 'view'){
                $this->createView($view, $dir);
            }
        }else{
            $this->writeln("Unable to create class {$name}", 'red');
        }
    }
    
     /**
     * Create a view.
     *
     * @param string  $name View name
     * @param string|null $dir  Directory path
     * 
     * @return void
     */
    private function createView(string $name, ?string $dir = null): void 
    {
        self::$engine ??= (new Template())->templateEngine;
        $name = strtolower($name);
        $type = (self::$engine === 'smarty') ? '.tpl' : '.php';
        $path = "/resources/views/";

        if ($dir !== null) {
            $path .= trim($dir, '/') . '/';
        }

        if(self::$engine === 'smarty'){
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
            $this->writeln("Unable to create view {$name}", 'red');
        }
    }

     /**
     * Create a model class.
     *
     * @param string  $name Model name.
     * @param string|null $implement  Model implement interface.
     * 
     * @return void
     */
    private function createModel(string $name, ?string $implement = null): void 
    {
        $namespace = ($implement !== null) ? "\nuse \\$implement;" : '';
        $extends = " extends BaseModel";
        $implement = ($implement !== null) ? " implements $implement" : '';
        $name = ucfirst($name);
        $table = strtolower($name) . '_table';

        $modelContent = <<<PHP
        <?php
        namespace App\Controllers\Models;

        use \Luminova\Base\BaseModel;
        use \DateTimeInterface;
        $namespace
        class $name$extends$implement
        {
            /**
             * The name of the model's table.
             * 
             * @var string \$table
            */
            protected string \$table = '$table'; 
        
            /**
             * The default primary key column.
             * 
             * @var string \$primaryKey
            */
            protected string \$primaryKey = ''; 
        
            /**
             * Searchable table column names.
             * 
             * @var array<int, string> \$searchables
            */
            protected array \$searchables = [];
        
            /**
             *  Enable database caching for query builder.
             * 
             * @var bool \$cachable
            */
            protected bool \$cachable = true; 
        
            /**
             * Database cache expiration time in seconds.
             * 
             * @var DateTimeInterface|int \$expiry
            */
            protected DateTimeInterface|int \$expiry = 7 * 24 * 60 * 60;
        
            /**
             * Specify whether the model's table is updatable, deletable, and insertable.
             * 
             * @var bool \$readOnly
            */
            protected bool \$readOnly = false; 
        
            /**
             * Fields that can be inserted.
             * 
             * @var array<int,string> \$insertables
            */
            protected array \$insertables = []; 
        
            /**
             * Fields that can be updated.
             * 
             * @var array \$updatables
            */
            protected array \$updatables = []; 
        
            /**
             * Input validation rules.
             * 
             * @var array<string,string> \$rules
            */
            protected array \$rules = [];
        
            /**
             * Input validation error messages for rules.
             * 
             * @var array<string,array> \$messages.
            */
            protected array \$messages = [];
        }
        PHP;

        $path = "/app/Controllers/Models/";
        
        if (!$this->saveFile($modelContent, $path, "{$name}.php")) {
            $this->writeln("Unable to create model {$name}", 'red');
        }
    }

    /**
     * Create a class.
     *
     * @param string $name      Class name
     * @param string|null $extend    Class to extend
     * @param string|null $implement Interface to implement
     * 
     * @return void
     */
    private function createUtilClass(string $name, ?string $extend = null, ?string $implement = null): void 
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
            $this->writeln("Unable to create class {$name}", 'red');
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
        $filepath = root($path) . $filename;
        $continue = 'yes';

        if(file_exists($filepath)){
            $continue = $this->prompt('File with same name "' . $filename .'", already exsit in path: "' . $path . '", do you want to continue?', ["yes", "no"], 'required|in_array(yes,no)');
        }

        if($continue === 'yes'){
            try {
                if(write_content($filepath, $content)){
                    $filepath = filter_paths($filepath);
                    $this->writeln("Completed succefully location: /{$filepath}", 'green');
                    return true;
                }
            } catch(Exception $e) {
                $this->writeln($e->getMessage(), 'red');
            }
        }

        return false;
    }
}