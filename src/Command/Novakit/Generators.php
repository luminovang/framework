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

use \Luminova\Base\BaseCommand;
use \Exception;

class Generators extends BaseCommand 
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
        '-extend'  => 'Extend class name',
        '-implement' => 'Implement class interface',
    ];

    protected string|array $usages  = [
        'php novakit create:controller "name" -extend "className"',
        'php novakit create:class "name" -extend "className" -implement "myInterface"',
        'php novakit create:view "name"',
        'php novakit create:class "name"'
    ];

    protected string $description = 'Create controller, view or class';

    /**
     * @param array $options terminal options
     * 
     * @return int 
    */
    public function run(?array $options = []): int
    {
        $this->explain($options);

        $command = trim($this->getCommand());
        $name = $this->getArgument(1);
        $extend = $this->getOption('extend');
        $implement = $this->getOption('implement');

        $runCommand = match($command){
            'create:controller' => $this->createController($name),
            'create:view' => $this->createView($name),
            'create:class' => $this->createClass($name, $extend, $implement),
            default => 'unknown'
        };

        if ($runCommand === 'unknown') {
            $this->error('Unknown command ' . $this->color("'$command'", 'red') . ' not found', null);

            return STATUS_ERROR;
        } 
            
        return (int) $runCommand;
    }

    private function createController(string $name): void 
    {
        $use = 'use Luminova\Base\BaseController;';
        $extend = 'BaseController';

        $name = ucfirst($name);

        $classContent =  "<?php\nnamespace App\Controllers;\n\n$use\nclass $name$extend\n{\n    public function __construct(){\n    }\n    // Class content goes here\n}";

        $path = "/app/Controllers/{$name}.php";
        
        if($this->saveFile($classContent, $path)){
            $this->writeln("Class created: {$path}", 'green');
        }else{
            $this->writeln("Unable to create class {$name}", 'red');
        }
    }

    private function createView(string $name): void 
    {

        $name = strtolower($name);

        $classContent =  "<!DOCTYPE html>\n<html lang=\"en\">\n    <head>\n        <link rel=\"shortcut icon\" href=\"<?php echo \$this->_base;?>favicon.png\" />\n<title>$name</title>\n   </head>\n   <body>\n        <h1>Welcome To $name</h1>\n    </body>\n</html>";

        $path = "/resources/views/{$name}.php";
        
        if($this->saveFile($classContent, $path)){
            $this->writeln("View created: {$path}", 'green');
        }else{
            $this->writeln("Unable to create view {$name}", 'red');
        }
    }

    private function createClass(string $name, bool|null|string $extend = null, bool|null|string $implement = null): void 
    {
        $use = $extend ? "use \\$extend;\n" : '';
        $use .= $implement ? "use \\$implement;" : '';

        $extend = $extend ? " extends $extend" : '';
        $implement = $implement ? " implements $implement" : '';

        $name = ucfirst($name);

        $classContent =  "<?php\nnamespace App\Controllers\Utils;\n\n$use\nclass $name$extend$implement\n{\n    public function __construct(){\n    }\n    // Class content goes here\n}";

        $path = "/app/Controllers/Utils/{$name}.php";
        
        if($this->saveFile($classContent, $path)){
            $this->writeln("Class created: {$path}", 'green');
        }else{
            $this->writeln("Unable to create class {$name}", 'red');
        }
    }

    private function saveFile(string $content, string $path): bool 
    {
        $filepath = root(__DIR__, $path);
        try {
            return write_content($filepath, $content);
        } catch(Exception $e) {
            $this->writeln($e->getMessage(), 'red');
            return false;
        }
    }
}