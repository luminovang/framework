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
use \App\Config\Template;
use \Exception;

class Generators extends BaseConsole 
{
    /**
     * {@inheritdoc}
    */
    protected string $group = 'Generators';

    /**
     * {@inheritdoc}
    */
    protected string $name = 'create:*';

    /**
     * {@inheritdoc}
    */
    protected array $usages = [
        'php novakit create:controller --help',
        'php novakit create:class --help',
        'php novakit create:view --help',
        'php novakit create:model --help',
    ];

    /**
     * {@inheritdoc}
    */
    private static ?string $engine = null;

    /**
     * {@inheritdoc}
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
        $module = strtolower(trim($this->getOption('module', '')));
        $hmvc = env('feature.app.hmvc', false);
        
        $runCommand = match($command){
            'create:controller' => $this->createController($name, $type, $dir, $module, $hmvc),
            'create:view'       => $this->createView($name, $dir, $module, $hmvc),
            'create:class'      => $this->createUtilClass($name, $extend, $implement),
            'create:model'      => $this->createModel($name, $implement, $module, $hmvc),
            default             => 'unknown'
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
     * Create a controller.
     *
     * @param string $name  Controller name.
     * @param string $type  The type of controller.
     * @param string|null $dir Directory path.
     * 
     * @return void
     */
    private function createController(
        string $name, 
        string $type = 'view', 
        ?string $dir = null, 
        string $module = '',
        bool $hmvc = false
    ): void 
    {
        $view = '';
        $namespace = $hmvc 
            ? 'namespace App\Modules\\' . ($module ==='' ? '' : uppercase_words($module) . '\\') . 'Controllers' 
            : 'namespace App\Controllers';

       if($type === 'command'){
            $use = 'use \Luminova\Base\BaseCommand;';
            $extend = 'BaseCommand';
        }else{
            $use = 'use \Luminova\Base\BaseController;';
            $extend = 'BaseController';
        }

        $class = uppercase_words($name);
        if($type === 'view' || $type === 'api'){
            $classContent = <<<PHP
            <?php
            $namespace\Http;
            
            $use
            
            class $class extends $extend
            {
                public function __construct()
                {
                    /**
                     * Constructor logic goes here.
                     * parent::__construct();
                    */
                }

            PHP;

            if($type === 'view'){
                $view = str_replace('controller', '', strtolower($name));
                $classContent .= <<<PHP
                    // Class methods goes here
                    public function main(): int
                    {
                        return \$this->view('$view', []);
                    }

                PHP;
            }
        }elseif($type === 'command'){
            $classContent = <<<PHP
            <?php
            $namespace\Cli;
            
            $use
            
            class $class extends $extend
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
            $this->writeln("Invalid controller --type flag: {$type}, use 'view or command'", 'red');
            return;
        }

        $classContent .='}';
        $path = ($hmvc 
            ? '/app/Modules/' . ($module === '' ? '' : $module . '/') . 'Controllers/' 
            : '/app/Controllers/') . ($type === 'command') ? 'Cli/' : 'Http/';
        
        if($this->saveFile($classContent, $path, "{$class}.php")){
            if($type === 'view'){
                $this->createView($view, $dir, $module);
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
    private function createView(
        string $name, 
        ?string $dir = null, 
        string $module = '',
        bool $hmvc = false
    ): void 
    {
        self::$engine ??= (new Template())->templateEngine;
        $name = strtolower($name);
        $type = (self::$engine === 'smarty') ? '.tpl' : '.php';
        $path = $hmvc 
            ? 'app/Modules/' . ($module === ''? '' :$module . '/') . 'Views'
            : '/resources/Views/';

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
    private function createModel(
        string $name, 
        ?string $implement = null,
        string $module = '',
        bool $hmvc = false
    ): void 
    {
        $interface = ($implement !== null) ? "\nuse \\$implement;" : '';
        $namespace = $hmvc 
            ? 'namespace App\Modules\\' . ($module ==='' ? '' :uppercase_words($module) . '\\') . 'Models\Controllers;' 
            : 'namespace App\Models;';
        $extends = " extends BaseModel";
        $implement = ($implement !== null) ? " implements $implement" : '';
        $name = uppercase_words($name);
        $table = strtolower($name) . '_table';

        $modelContent = <<<PHP
        <?php
        $namespace

        use \Luminova\Base\BaseModel;
        use \DateTimeInterface;
        $interface
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
             * @var array<int,string> \$searchable
            */
            protected array \$searchable = [];
        
            /**
             *  Enable database caching for query builder.
             * 
             * @var bool \$cacheable
            */
            protected bool \$cacheable = true; 
        
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
             * @var array<int,string> \$insertable
            */
            protected array \$insertable = []; 
        
            /**
             * Fields that can be updated.
             * 
             * @var array \$updatable
            */
            protected array \$updatable = []; 
        
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
     
        $path = $hmvc
            ? '/app/Modules/' . ($module === ''? '' : $module . '/') . 'Models'
            : '/app/Models/';
        
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
        $name = uppercase_words($name);


        $classContent = <<<PHP
        <?php
        namespace App\Utils;

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

        $path = "/app/Utils/";
        
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
            $continue = $this->prompt('File with same name "' . $filename .'", already exist in path: "' . $path . '", do you want to continue?', ["yes", "no"], 'required|in_array(yes,no)');
        }

        if($continue === 'yes'){
            try {
                if(write_content($filepath, $content)){
                    $filepath = filter_paths($filepath);
                    $this->writeln("Completed successfully location: /{$filepath}", 'green');
                    return true;
                }
            } catch(Exception $e) {
                $this->writeln($e->getMessage(), 'red');
            }
        }

        return false;
    }
}