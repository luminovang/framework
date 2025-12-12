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
namespace Luminova\Base;

use \Luminova\Boot;
use \App\Application;
use \Luminova\Utility\MIME;
use \Luminova\Command\Input;
use \Luminova\Utility\Helpers;
use \Luminova\Command\Terminal;
use function \Luminova\Funcs\make_dir;
use \Luminova\Components\Object\LazyObject;
use \Luminova\Foundation\Core\Application as CoreApplication;
use \Luminova\Interface\{RoutableInterface, LazyObjectInterface};

/**
 * A class to extend when building a CLI controller for routable commands.
 * It specialize with handling commands using Luminova routing system and must navigate to `public/index.php` to run registered commands.
 * 
 * @property string $group  @inheritDoc
 * @property string $name   @inheritDoc
 * @property array|string $usages @inheritDoc
 * @property array $options @inheritDoc
 * @property array $examples @inheritDoc
 * @property string $description @inheritDoc
 * @property array $users @inheritDoc
 * @property array|null $authentication @inheritDoc
 * 
 * @see https://luminova.ng/docs/0.0.0/controllers/cli-controller
 */
abstract class Command implements RoutableInterface
{
    /**
     * Command group name for the current controller class.
     * 
     * @var string $group
     * The group name is used for registering command routes.
     * Only methods within the same group are registered together.
     * Example: `php index.php <command-group-name> <command> <arguments>`.
     */
    protected string $group = '';

    /**
     * Command name for the current controller class.
     * 
     * @var string $name
     * The command name is used internally to generate command information, 
     * such as for displaying help.
     */
    protected string $name = '';

    /**
     * Command usage instructions.
     * Keys represent the command usage, and values describe the usage.
     * 
     * @var array<string|int,string>|string $usages
     */
    protected array|string $usages = '';

    /**
     * Available command options.
     * Keys represent the options (e.g., `'-f, --foo' => 'Foo description'`).
     * 
     * @var array<string|int,string> $options
     */
    protected array $options = [];

    /**
     * Examples demonstrating command usage.
     * 
     * @var array<string|int,string> $examples
     */
    protected array $examples = [];

    /**
     * Full description of the command.
     * Provides detailed information about the command's purpose.
     * 
     * @var string $description
     */
    protected string $description = '';

    /**
     * List of allowed system users.
     * 
     * @var array<int,string> $users
     * 
     * @example - Users:
     * 
     * Only users listed here can execute this command, leave empty array to allow all users.
     * 
     * ```php
     * ['www-data', 'ubuntu', 'admin']
     * ```
     */
    protected array $users = [];

    /**
     * Authenticate configuration for password or private/public login.
     * 
     * @var array<string,mixed>|null $authentication
     * 
     * @example - Auth from database:
     * ```php
     * [
     *      'storage' => 'database',
     *      'tableName' => 'foo_cli_users'
     * ]
     * ```
     * @example - Auth from filesystem:
     * ```php
     * [
     *      'storage' => 'filesystem',
     *      'storagePath' => 'writeable/auth/cli_users.php'
     * ]
     * ```
     * @example - Supported Array keys Or Database column names:
     * 
     * - **auth:** `(string)` - Authentication type (e.g, `password` or `key`).
     * - **content:** `(string)` - Password hash or empty for not password login, public key content or public key file.
     * - **updated_at:** `datetime` - Last authenticated datetime.
     */
    protected ?array $authentication = null;

    /**
     * Lazy loaded application instance.
     * 
     * @var CoreApplication|Application<CoreApplication> $app
     */
    protected ?LazyObjectInterface $app = null;

    /**
     * Command input. 
     * 
     * @var Input $input
     */
    protected ?Input $input = null;

    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        Terminal::init();

        $this->app = LazyObject::newObject(fn(): CoreApplication => Boot::application());
        $this->onCreate();
    }

    /**
     * Parse command input.
     * 
     * This parses and processes command-line arguments and options, making them accessible in console controllers.
     * 
     * @param array<string,mixed> $command Command arguments, options, and flags extracted from CLI execution.
     * 
     * @return Input Returns instance of input.
     */
    public final function parse(array $command): Input
    {
        return $this->input = new Input($command);
    }

    /**
     * Allows access to protected methods.
     *
     * @param string $method The method name to call.
     * @param array<int,mixed> $arguments The arguments to pass to the method.
     * 
     * @return mixed Return the value of the method, or null if the method doesn't exist.
     * @ignore 
     */
    public function __call(string $method, array $arguments): mixed
    {
        return method_exists($this->input, $method) 
            ? $this->input->{$method}(...$arguments) 
            : null;
    }

    /**
     * Override the default help display.
     * Implement custom help display, returning STATUS_SUCCESS on success, otherwise return STATUS_ERROR.
     *
     * @param array<string,mixed> $helps Information about the command:
     *      - class: The class name (may not be available if extending BaseConsole).
     *      - group: The group name.
     *      - name: The command name.
     *      - description: The command description.
     *      - usages: Command usage examples.
     *      - options: Available command options.
     *      - examples: Command usage examples.
     * 
     * @return int Return exit code STATUS_SUCCESS when custom help is implemented, 
     *          STATUS_ERROR when using default implementation.
     */
    abstract public function help(array $helps): int;

    /**
     * Retrieves the full command-line execution string for the current command.
     *
     * @return string Return the full command-line string.
     * 
     * @example - Example: 
     * 
     * Given a command with `php index.php foo user=1 --no-nocache`
     * 
     *```bash 
     * index.php index.php foo user=1 --no-nocache
     * ```
     */
    protected function getString(): string 
    {
        return $this->input->getInput();
    }

    /**
     * Retrieves an option value of executed command based on the specified key or alias.
     * This method is an alias of the parent `getAnyOption` method.
     * 
     * @param string $key The primary key for the option.
     * @param string|null $alias The alias for the option.
     * @param mixed $default The default value to return if the option is not found.
     * 
     * @return mixed Return the value of the option, or the default if not found.
     * 
     * @example - Given a command with options `--verbose` or `-v`
     * ```php
     * $verbose = $this->option('verbose', 'v', false); // Returns true if either option is set.
     * ```
     * @example - Example: 
     * 
     * Given a command with options `--address=192.160.0.1` or `-a=192.160.0.1`.
     * 
     * ```php
     * $address = $this->option('address', 'a', null); // Returns 192.160.0.1 if either option is set.
     */
    protected function option(string $key, ?string $alias = null, mixed $default = false): mixed 
    {
        return ($alias === null) 
            ? $this->input->getOption($key, $default)
            : $this->input->getAnyOption($key, $alias, $default);
    }

    /**
     * Retrieves a command-line argument by its index or name, extending the parent functionality.
     * 
     * If the argument is specified as a string (name), it searches all arguments for a match.
     * If the argument is in "key=value" format, the value portion (after `=`) is returned.
     * If an integer index is provided, it retrieves the argument by position.
     * 
     * @param int|string $index The index (integer) or name (string) of the argument.
     * 
     * @return mixed Return the argument value, or the full argument if no '=' is found, or null if not found.
     * 
     * @example - Example:
     * 
     * Given a command with arguments 'php index.php foo file=example.txt mode=write'.
     * 
     * ```php
     * $file = $this->argument('file'); // Returns 'example.txt'
     * $file = $this->argument(1); // Returns 'example.txt'
     * $mode = $this->argument('mode'); // Returns 'write'
     * $mode = $this->argument(2);    // Returns 'write'
     * ```
     */
    protected function argument(string|int $index): mixed 
    {
        if (is_string($index)) {
            foreach ($this->input->getArguments() as $arg) {
                if (str_starts_with($arg, $index)) {
                    if(str_contains($arg, '=')){
                        $value = explode('=', $arg, 2)[1] ?? null;

                        if($value === null){
                            return null;
                        }

                        return trim($value);
                    }

                    return $arg;
                }
            }
        }

        return $this->input->getArgument($index, true);
    }

    /**
     * Uploads a file in command line interface to a specified directory.
     *
     * @param string $file The path to the file, text content or a base64 encoded string of the file content.
     * @param string $directory The target directory where the file should be uploaded.
     * @param string|null $name Optional. The desired name for the uploaded file (e.g, `file.txt`). 
     *                      If null, a name will be generated.
     *
     * @return string|false Returns the path of the uploaded file on success, or false on failure.
     */
    protected function upload(string $file, string $directory, ?string $name = null): string|bool
    {
        if(!$file){
            $this->error('Failed no content to upload.');
            return false;
        }

        if (is_file($file)){
            if(file_exists($file)) {
                $data = file_get_contents($file);
                if ($data === false) {
                    $this->error("Failed to read file {$file} content.");
                    return false;
                }
            } else{
                $this->error("The file {$file} does not exist.");
                return false;
            }

            $name = basename($name ?? $file);
        }elseif(Helpers::isBase64Encoded($file)) {
            $data = base64_decode($file);

            if ($data === false) {
                $this->error("Failed to decode base64 content.");
                return false;
            }

            if($name === null){
                $mime = MIME::guess($data);
                $ext = ($mime === false) ? 'bin' : MIME::findExtension($mime);

                $name = uniqid(date('YmdHis') . '_') . '.' . ($ext ?: 'bin');
            }else{
                $name = basename($name);
            }
        }else{
            $data = $file;
            $name = basename($name ?? uniqid(date('YmdHis') . '_') . '.txt');
        }

        if(!make_dir($directory)){
            $this->error("The directory {$directory} does not exist, and creation failed.");
            return false;
        }

        $output = rtrim($directory, '/') . '/' . $name;

        if(file_put_contents($output, $data) !== false){
            return $output;
        }

        $this->error("Failed to upload file: {$output} to directory: {$directory}.");
        return false;
    }

    /**
     * onCreate method that gets triggered on object creation, 
     * designed to be overridden in subclasses for custom initialization.
     * 
     * @return void
     */
    protected function onCreate(): void {}

    /**
     * Getter for protected properties.
     *
     * @param string $key The property name.
     * 
     * @return mixed Return the property value, or null if it doesn't exist.
     * @ignore
     */
    public function __get(string $key): mixed
    {
        return $this->{$key} ?? null;
    }
    
    /**
     * Checks if a property is set.
     *
     * @param string $key The property name.
     * 
     * @return bool Return true if the property exists, otherwise false.
     * @ignore
     */
    public function __isset(string $key): bool
    {
        return property_exists($this, $key);
    }
}