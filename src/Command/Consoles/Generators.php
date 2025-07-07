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

use \Luminova\Luminova;
use \Luminova\Base\BaseConsole;
use \App\Config\Template;
use \Exception;
use function \Luminova\Funcs\{
    root,
    filter_paths,
    pascal_case,
    write_content
};

class Generators extends BaseConsole 
{
    /**
     * {@inheritdoc}
     */
    protected string $group = 'create';

    /**
     * {@inheritdoc}
     */
    protected string $name = 'Generators';

    /**
     * {@inheritdoc}
     */
    protected array|string $usages = [
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
        $this->term->perse($options);
        $command = trim($this->term->getCommand());
        $name = $this->term->getArgument(1);

        if(empty($name)){
            $this->term->writeln('Generator name is required', 'red');
            $this->term->beeps();

            return STATUS_ERROR;
        }

        $extend = $this->term->getAnyOption('extend', 'e', null);
        $implement = $this->term->getAnyOption('implement', 'i', null);
        $dir = $this->term->getAnyOption('dir', 'd', '');
        $module = strtolower(trim($this->term->getAnyOption('module', 'm', '')));
        $hmvc = env('feature.app.hmvc', false);
        
        $runCommand = match($command){
            'create:controller' => $this->createController(
                $name, 
                strtolower($this->term->getOption('type', 'view')), 
                $this->term->getAnyOption('template', 't', ''), 
                $module, 
                $hmvc, 
                $implement
            ),
            'create:view'       => $this->createView($name, $dir, $module, $hmvc),
            'create:class'      => $this->createUtilClass($name, $extend, $implement),
            'create:model'      => $this->createModel($name, $implement, $module, $hmvc),
            default             => 'unknown'
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
        string|bool $template = false, 
        string $module = '',
        bool $hmvc = false,
        ?string $implement = null
    ): void 
    {
        $view = '';
        $prefix = '';
        $use = 'use \Luminova\Base\\';
        $implements = '';
        $module = $module ? pascal_case($module) : '';
        $namespace = 'namespace App\Console';

        if($type === 'command' || $type === 'view'){
            $namespace = $hmvc 
                ? 'namespace App\Modules\\' . ($module ? $module . '\\' : '') . 'Controllers' 
                : 'namespace App\Controllers';
        }
        
        $onHmvcCreate = ($hmvc  && $module) ? "\$this->app->setModule('$module');\n" : '';
        
        if($type === 'command'){
            $use .= "BaseCommand;\n";
            $use .= "use \Luminova\Attributes\Group;\n";

            $extend = 'BaseCommand';
            $namespace .= '\\Cli';
            $prefix = "#[Group(name: 'my-command-name')]";
        }elseif($type === 'console'){
            $use .= 'BaseConsole;';
            $extend = 'BaseConsole';
        }else{
            $limitation = $module ? strtolower($module) : '';

            $use .= "BaseController;\n";
            $use .= "use \Luminova\Attributes\Prefix;\n";
            $use .= "use \Luminova\Attributes\Route;\n";
            $use .= 'use \App\Errors\Controllers\ErrorController;';

            $namespace .= '\\Http';
            $prefix = "#[Prefix(pattern: '/$limitation.*', onError: [ErrorController::class, 'onWebError'])]";
            $extend = 'BaseController';
        }

        if($implement){
            $implements =  ' implements ' . Luminova::getClassBaseNames($implement);
            $use .= 'use ' . implode(";\nuse ", explode(',', $implement)) . ';';
        }

        $class = pascal_case($name);
        $classContent = <<<PHP
        <?php
        $namespace;
        
        $use

        $prefix
        class $class extends $extend$implements

        PHP;
        if($type === 'view'){
            $classContent .= <<<PHP
            {
                protected function onCreate(): void
                {
                    $onHmvcCreate// Constructor logic goes here.
                }\n
            PHP;

            if($type === 'view'){
                $classContent .= <<<PHP

                    /**
                     * Controller main method.
                     * 
                     * @return int Return exit status code.
                     */
                    #[Route('/$limitation', methods: ['GET'])]
                    public function main(): int
                    {
                        return \$this->view('index', ['test' => 'testValue']);
                    }\n
                PHP;
            }
        }elseif($type === 'command' || $type === 'console'){
            $classContent .= <<<PHP
            {
                /**
                 * {@inheritdoc}
                 */
                protected string \$group = 'my-command-name';

                /**
                 * {@inheritdoc}
                 */
                protected string \$name  = 'my-command';

                /**
                 * {@inheritdoc}
                 */
                protected array|string \$usages = '';

                /**
                 * {@inheritdoc}
                 */
                protected array \$options = [];

                /**
                 * {@inheritdoc}
                 */
                protected array \$examples = [];

                /**
                 * {@inheritdoc}
                 */
                protected string \$description = '';

                /**
                 * {@inheritdoc}
                 */
                protected array \$users = [];

                /**
                 * {@inheritdoc}
                 */
                protected ?array \$authentication = null;

                /**
                 * {@inheritdoc}
                 *
                public function help(array \$helps): int
                {
                    return STATUS_ERROR;
                }\n
            PHP;

            if($type === 'command'){
                $classContent .= <<<PHP
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
                $classContent .= <<<PHP
                    /**
                     * {@inheritdoc}
                     * 
                     * @return int Return status code.
                     */
                    public function run(?array \$options = []): int
                    {
                        \$this->header();
                        return STATUS_SUCCESS;
                    }
                PHP;
            }
        }else{
            $this->term->writeln("Invalid controller --type flag: {$type}, use 'view or command'", 'red');
            return;
        }

        $classContent .='}';
        $path = '/app/Console/';

        if($type === 'command' || $type === 'view'){
            $path = ($hmvc 
                ? '/app/Modules/' . ($module ? $module . '/' : '') . 'Controllers/' 
                : '/app/Controllers/');
            $path .= ($type === 'command') ? 'Cli/' : 'Http/';
        }
        
        if($this->saveFile($classContent, $path, "{$class}.php")){
            if($type === 'view' && $template){
                $this->createView(
                    $view, 
                    ($template === true) ? '' : $template, 
                    $module
                );
            }
        }else{
            $this->term->writeln("Unable to create class {$name}", 'red');
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
        $module = $module ? pascal_case($module) : '';
        $name = strtolower($name);
        $type = (self::$engine === 'smarty') ? '.tpl' : '.php';
        $path = $hmvc 
            ? 'app/Modules/' . ($module ? $module . '/' : '') . 'Views'
            : '/resources/Views/';

        if ($dir) {
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
            $this->term->writeln("Unable to create template view '{$name}'", 'red');
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
        $module = $module ? pascal_case($module) : '';
        $interface = $implement ? "\nuse \\$implement;\n" : '';
        $implementClass = Luminova::getClassBaseNames($implement);
        $namespace = $hmvc 
            ? 'namespace App\Modules\\' . ($module ? $module . '\\' : '') . 'Models;' 
            : 'namespace App\Models;';
        $extends = " extends BaseModel";
        $implement = $implementClass ? " implements $implementClass" : '';
        $name = pascal_case($name);
        $table = strtolower($module ? "{$name}_{$module}" : $name) . '_table';

        $modelContent = <<<PHP
        <?php
        $namespace

        use \Luminova\Base\BaseModel;
        use \Luminova\Database\Builder;
        use \Luminova\Security\Validation;
        use \DateTimeInterface;
        $interface
        class $name$extends$implement
        {
            /**
             * {@inheritdoc}
             * 
             * Change <$table> to your actual database table name.
             */
            protected string \$table = '$table'; 

            /**
             * {@inheritdoc}
             */
            protected string \$primaryKey = ''; 

            /**
             * {@inheritdoc}
             */
            protected bool \$cacheable = true; 

            /**
             * {@inheritdoc}
             */
            protected string \$resultType = 'object';

            /**
             * {@inheritdoc}
             */
            protected static string \$cacheFolder = '';

            /**
             * {@inheritdoc}
             */
            protected bool \$readOnly = false; 

            /**
             * {@inheritdoc}
             */
            protected array \$searchable = [];

            /**
             * {@inheritdoc}
             */
            protected array \$insertable = []; 

            /**
             * {@inheritdoc}
             */
            protected array \$updatable = []; 

            /**
             * {@inheritdoc}
             */
            protected array \$rules = [];

            /**
             * {@inheritdoc}
             */
            protected array \$messages = [];

            /**
             * {@inheritdoc}
             */
            protected DateTimeInterface|int \$expiry = 7 * 24 * 60 * 60;

            /**
             * {@inheritdoc}
             */
            protected static ?Validation \$validation = null;

            /**
             * {@inheritdoc}
             */
            protected ?Builder \$builder = null;


            /**
             * {@inheritdoc}
             */
            protected function onCreate(): void {}
        }
        PHP;
     
        $path = $hmvc
            ? '/app/Modules/' . ($module ? $module . '/' : '') . 'Models'
            : '/app/Models/';
        
        if (!$this->saveFile($modelContent, $path, "{$name}.php")) {
            $this->term->writeln("Unable to create database model '{$name}'", 'red');
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

        $extendClass = Luminova::getClassBaseNames($extend);
        $implementClass = Luminova::getClassBaseNames($implement);

        $extendString = $extendClass ? " extends $extendClass" : '';
        $implementString = $implementClass ? " implements $implementClass" : '';
        $name = pascal_case($name);


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
            $this->term->writeln("Unable to create class '{$name}'", 'red');
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
        $filepath = root($path, $filename);
        $continue = 'yes';

        if(file_exists($filepath)){
            $this->term->writeln(
                "A file named '{$filename}' already exists at '{$path}'.", 
                'yellow'
            );
            
            $continue = $this->term->prompt(
                'Do you want to override it?', 
                ['yes', 'no'], 
                'required|in_array(yes,no)'
            );            
        }

        if($continue === 'yes'){
            try {
                if(write_content($filepath, $content)){
                    $filepath = filter_paths($filepath);
                    $this->term->writeln("Completed successfully location: /{$filepath}", 'green');
                    return true;
                }
            } catch(Exception $e) {
                $this->term->writeln($e->getMessage(), 'red');
            }
        }

        return false;
    }
}