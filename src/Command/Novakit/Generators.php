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

use \Luminova\Command\Terminal;
use \Luminova\Base\BaseCommand;
use \Closure;
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
        Terminal::registerCommands($options, false);

        $command = trim(Terminal::getCommand());
        $name = Terminal::getArgument(1);
        $extend = Terminal::getOption('extend');
        $implement = Terminal::getOption('implement');

        //if('-help' === $name || '--help' === $name){}

        $runCommand = match($command){
            'create:controller' => $this->createController($name),
            'create:view' => $this->createView($name),
            'create:class' => $this->createClass($name, $extend, $implement),
            default => function(): int {
                echo "Handle Unknown command\n";

                return STATUS_ERROR;
            }
        };

        if ($runCommand instanceof Closure) {
            return (int) $runCommand();
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
            Terminal::writeln("Class created: {$path}", 'green');
        }else{
            Terminal::writeln("Unable to create class {$name}", 'red');
        }
    }

    private function createView(string $name): void 
    {

        $name = strtolower($name);

        $classContent =  "<!DOCTYPE html>\n<html lang=\"en\">\n    <head>\n        <link rel=\"shortcut icon\" href=\"<?php echo \$this->_base;?>favicon.png\" />\n<title>$name</title>\n   </head>\n   <body>\n        <h1>Welcome To $name</h1>\n    </body>\n</html>";

        $path = "/resources/views/{$name}.php";
        
        if($this->saveFile($classContent, $path)){
            Terminal::writeln("View created: {$path}", 'green');
        }else{
            Terminal::writeln("Unable to create view {$name}", 'red');
        }
    }

    private function createClass(string $name, ?string $extend = null, ?string $implement = null): void 
    {
        $use = $extend ? "use \\$extend;\n" : '';
        $use .= $implement ? "use \\$implement;" : '';

        $extend = $extend ? " extends $extend" : '';
        $implement = $implement ? " implements $implement" : '';

        $name = ucfirst($name);

        $classContent =  "<?php\nnamespace App\Controllers\Utils;\n\n$use\nclass $name$extend$implement\n{\n    public function __construct(){\n    }\n    // Class content goes here\n}";

        $path = "/app/Controllers/Utils/{$name}.php";
        
        if($this->saveFile($classContent, $path)){
            Terminal::writeln("Class created: {$path}", 'green');
        }else{
            Terminal::writeln("Unable to create class {$name}", 'red');
        }
    }

    private function saveFile(string $content, string $path): bool 
    {
        $filepath = root(__DIR__, $path);
        try {
            return write_content($filepath, $content);
        } catch(Exception $e) {
            Terminal::writeln($e->getMessage(), 'red');
            return false;
        }
    }
}