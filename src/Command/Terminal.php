<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Command;

use \Luminova\Application\Foundation;
use \Luminova\Command\Colors;
use \Luminova\Command\Console;
use \Luminova\Command\TextUtils;
use \Luminova\Security\InputValidator;
use \Luminova\Command\Novakit\Commands;
use \Luminova\Exceptions\InvalidArgumentException;
use \Closure;

class Terminal 
{
    /**
     * Height of terminal visible window
     *
     * @var int|null $height
    */
    protected static $height;

    /**
     * Width of terminal visible window
     *
     * @var int|null $width
    */
    protected static $width;

    /**
     * Is the readline library on the system?
     *
     * @var bool
     */
    public static $isReadline = false;

    /**
     * Prompt message display 
     *
     * @var string $waitMessage
     */
    private static $waitMessage = 'Press any key to continue...';

    /**
     * Write in a new line enabled
     *
     * @var bool $isNewline
    */
    protected static $isNewline = false;

    /**
     * Is colored text supported
     *
     * @var bool $isColored
    */
    protected static $isColored = false;

    /**
     * Passed command line arguments
     * And infos about command
     *
     * @var array $commandsOptions
    */
    protected static $commandsOptions = [];

    /**
     * Initialize command line instance before running any commands.
    */
    public function __construct()
    {
        defined('STDOUT') || define('STDOUT', 'php://output');
        defined('STDIN') || define('STDIN', 'php://stdin');
        defined('STDERR') || define('STDERR', 'php://stderr');
        
        static::$isReadline = extension_loaded('readline');
        static::$commandsOptions  = [];
        static::$isColored = static::isColorSupported(STDOUT);
    }

    /**
     * Show a waiting countdown, intentionally freeze screen while waiting
     * Or ask user for a key press to continue.
     * 
     * Examples
     * 
     * @example $this->waiting(20, true); show waiting for 20 seconds with countdown message
     * @example $this->waiting(0, false); show waiting message till user press any key
     * @example $this->waiting(20, false); show waiting for 20 seconds with a freezed screen
     *
     * @param int  $seconds Number of seconds for waiting
     * @param bool $countdown Show waiting countdown
     *
     * @return void
     */
    protected static final function waiting(int $seconds, bool $countdown = false): void
    {
        if ($seconds <= 0) {
            if (!$countdown) {
                static::writeln(self::$waitMessage);
                static::input();
            }
            return;
        }

        if ($countdown) {
            for ($time = $seconds; $time > 0; $time--) {
                static::fwrite("Waiting... ($time seconds) "  . PHP_EOL);
                static::clear();
                sleep(1);
            }
            static::clear();
            static::writeln("Waiting... (0 seconds)"  . PHP_EOL);
        } else {
            sleep($seconds);
        }
    }

    /**
     * Displays a progress bar on the CLI.
     * Progress should be called in a loop
     * Or use watcher()
     * 
     * Examples 
     * 
     * @example $this->progress(1, 10, true); Show progress bar line with beep when completed
     * @example $this->progress(1, 10, false); Show progress bar line without beep when completed
     *
     * @param int|bool $progressLine Current loop index number or false to terminate the progress bar
     * @param int|null $progressCount Total count of progress bar to show or null to on termination
     * @param bool $beep Beep when progress is completed, default is true
     *
     * @return float|int
    */
    protected static final function progress(int|bool $progressLine = 1, ?int $progressCount = 10, bool $beep = true): int|float
    {
        if ($progressLine === false || $progressCount === null) {
            if($beep){
                static::beeps(1);
            }
            return 100;
        }

        // Avoid division by zero or negative numbers
        $progressLine = max(0, $progressLine);
        $progressCount = max(1, $progressCount);

        // Calculate the progress bar width
        $percent = min(100, max(0, ($progressLine / $progressCount) * 100));
        $barWidth = (int) round($percent / 10);

        // Create the progress bar
        $progressBar = '[' . str_repeat('#', $barWidth) . str_repeat('.', 10 - $barWidth) . ']';

        // Textual representation
        $progressText = sprintf(' %3d%% Complete', $percent);

        // Write the progress bar and text
        static::fwrite("\033[32m" . $progressBar . "\033[0m" . $progressText . PHP_EOL);

        if ($progressLine <= $progressCount) {
            static::clear();
        }

        return $percent;
    }

    /**
     * Displays a progress bar on the CLI with a callback functions
     * Progress shouldn't be called in a loop
     * You can pass your function to execute in $stepCallback callback function
     * This is useful when you just want to display a progress bar 
     * and execute next method when it finished
     *
     * Examples 
     * 
     * @example $this->watcher(100, Closure, Closure, true) Show 100 lines of progress bar with a callbacks and beep on finish
     * 
     * @param int $limit Total count of progress bar to show.
     * @param Closure|null $onFinish(): void Execute callback when progress finished.
     * @param Closure|null $onProgress(int $progress):void Execute callback on each progress step.
     * @param bool $beep Beep when progress is completed, default is true.
     *
     * @return void
    */
    protected static final function watcher(int $limit, ?Closure $onFinish = null, ?Closure $onProgress = null, bool $beep = true): void 
    {
        $progress = 0;
    
        for ($step = 1; $step <= $limit; $step++) {
            if ($progress < 100) {
                $progress = static::progress($step, $limit);
                if ($onProgress instanceof Closure) {
                    $onProgress($progress);
                }
            }
    
            usleep(100000); 
            if ($progress >= 100) {
                break;
            }
        }

        static::progress(false, null, $beep);
        if ($onFinish instanceof Closure) {
            static::newLine();
            $onFinish();
        }
    }
  
    /**
     * Beeps a certain number of times.
     *
     * Usages
     * @example $this->beeps(1) Beep once 
     * 
     * @param int $num The number of times to beep
     *
     * @return void
    */
    protected static final function beeps(int $num = 1): void
    {
        echo str_repeat("\x07", $num);
    }

    /**
     * Prompt for user for to input a text, pass options as an array ["YES", "NO"].
     * Optionally, you can make a colored options by use the array key for color name ["green" => "YES","red" => "NO"]
     *
     * Examples
     *
     * @example $name = $this->prompt('What is your name?'); Prompt user to enter their name
     * @example $color = $this->prompt('Are you sure you want to continue?', ["green" => "YES","red" => "NO"]); Prompt user to choose any option and specify each option color in array key
     * @example $color = $this->prompt('What is your gender?', ['male','female']); Prompt user to select their gender, no colored text will be used
     * @example $email = $this->prompt('Are you sure you want to continue?', ["YES", "NO], 'required|in_array(YES,NO)'); Prompt user to choose any option and pass a validation
     *
     * @param string $message Prompt message.
     * @param array $options  Options to prompt selection, 
     * @param string|null $validations Validation rules.
     * @param bool $silent Print validation failure message parameter is true (default: false).
     *
     * @return string Return The user input.
    */
    protected static final function prompt(string $message, array $options = [], ?string $validations = null, bool $silent = false): string
    {
        $default = '';
        $placeholder = '';
        $textOptions = [];
        
        if($options !== []){
            foreach($options as $color => $text){
                $textOptions[] = $text;
                $placeholder .= static::color($text, $color) . ',';
            }
            $placeholder = '[' . rtrim($placeholder, ',') . ']';
            $default = $textOptions[0];
        }

        $validationRules = $validations ?? $textOptions;
        $validationRules = ($validations === 'none' 
            ? false : (is_array($validationRules) && $validationRules !== []
            ? "in_array('" .  implode("', '", $validationRules) . "')" : $validationRules));

        if ($validationRules && strpos($validationRules, 'required') !== false) {
            $default = '';
        }

        do {
            if(!$silent){
                if (isset($input)) {
                    static::fwrite("Input validation failed. ");
                }
                static::fwrite($message . ' ' . $placeholder . ': ');
            }
            $input = trim(static::input()) ?: $default;
        } while ($validationRules !== false && !static::validate($input, ['input' => $validationRules]));
    

        return $input;
    }

    /**
     * Prompt multi choice selection
     * Display array index key as the option identifier to select.
     * If you use associative array users will still see index key instead
     * 
     * Examples
     *
     * @example $array = $this->chooser('Choose your programming languages?', ['PHP', 'JAVA', 'SWIFT', 'JS', 'SQL', 'CSS', 'HTML']); Prompt multiple chooser, using PHP as default if user didn't select anything before hit return.
     * @example $array = $this->chooser('Choose your programming languages?', ['PHP', 'JAVA', 'SWIFT', 'JS', 'SQL', 'CSS', 'HTML'], true); Prompt multiple chooser, persisting that user must choose an option
     *
     *
     * @param string $text  Display text description for your multiple options
     * @param array  $options A list of options ['male' => 'Male', 'female' => 'Female] or ['male', 'female']
     * @param bool $required Require user to choose any option else the first array will be return as default
     *
     * @return array<string|int, mixed> $options The selected array keys and values
     * @throws InvalidArgumentException
    */
    protected static final function chooser(string $text, array $options, bool $required = false): array
    {
        if ($options == []) {
            throw new InvalidArgumentException('No options to select from were provided');
        }

        $lastIndex = 0;
        $defaultInput = ($required ? '__strictly_required__' : 0);
        $placeholder = 'To specify multiple values, separated by commas.';
        $validationRules = '';
        $optionValues = [];
        $index = 0;
        foreach ($options as $key => $value) {
            $optionValues[$index] = [
                'key' => $key,
                'value' => $value
            ];
            $validationRules .= $index . "','";
            $lastIndex = $index;
            $index++;
        }

        $validationRules = "keys_exist('" . rtrim($validationRules, ",'")  . "')";
        $lastIndex = (string) $lastIndex;

        static::writeln($text);
        self::writeOptions($optionValues, strlen($lastIndex));
        static::writeln($placeholder);

        do {
            if (isset($input)) {
                static::fwrite("Please select correct options from list.");
                static::newLine();
            }
            $input = trim(static::input());
            if($input === ''){
                $input = $defaultInput;
            }
        } while (!static::validate($input, ['input' => $validationRules]));

        $inputArray = list_to_array($input);
        $input = self::getInputValues($inputArray, $optionValues);

        return $input;
    }

    /**
     * Execute a system command.
     * 
     * @param string $command The command to execute.
     * 
     * @return array|int The output of the command as an array of lines, or false on failure
     */
    public final function execute(string $command): array|int
    {
        exec($command, $output, $returnCode);
        
        if ($returnCode === STATUS_SUCCESS) {
            return $output;
        }

        return STATUS_ERROR;
    }

    /**
     * Return user selected options
     * Get Input Array Values
     * 
     * @param array $input user input as array.
     * @param array $options options .
     * 
     * @return array<string|int,mixed> $options The selected array keys and values
    */
    private static function getInputValues(array $input, array $options): array
    {
        $result = [];
        foreach ($input as $value) {
            if (isset($options[$value]['key'], $options[$value]['value'])) {
                $result[$options[$value]['key']] = $options[$value]['value'];
            }
        }
        return $result;
    }

    /**
     * Display select options with key index as an identifier
     * 
     * @param array<string,mixed> $options options.
     * @param int $max Paddend end max.
     * 
     * @return void 
    */
    private static function writeOptions(array $options, int $max): void
    {
        foreach ($options as $key => $value) {
            $name = TextUtils::padEnd('  [' . $key . ']  ', $max, ' ');
            static::writeln(static::color($name, 'green') . static::wrap($value['value'], 125, $max));
        }
        static::newLine();
    }

    /**
     * Wrap it with padding left and width to a maximum

     * @param string|null $string string to write
     * @param int $max maximum width
     * @param int $leftPadding left padding
     * 
     * @return string $lines
    */
    public static final function wrap(?string $string = null, int $max = 0, int $leftPadding = 0): string
    {
        if (empty($string)) {
            return '';
        }
        $max = min($max, static::getWidth());
        $max -= $leftPadding;

        $lines = wordwrap($string, $max, PHP_EOL);

        if ($leftPadding > 0) {
            $lines = preg_replace('/^/m', str_repeat(' ', $leftPadding), $lines);
        }

        return $lines;
    }

    /**
     * Create a card text.
     *
     * @param string $text string to pad
     * @param int|null $padding maximum padding
     * 
     * @return string Return beautiful card text.
    */
    public static final function card(string $text, ?int $padding = null): string 
    {
        $width = static::getWidth() / 2;
        $padding ??= $width;
        $padding = max(20, $padding);
        $padding = (int) min($width, $padding);

        $text = static::wrap($text, $padding);
        $largest = TextUtils::largest($text)[1];
        $lines = explode(PHP_EOL, $text);
        $text = str_repeat(' ', $largest + 2) . PHP_EOL;

        foreach ($lines as $line) {
            $length = max(0, $largest - TextUtils::strlen($line));
            $text .= ' ' . $line . str_repeat(' ', $length) . ' '  . PHP_EOL;
        }
        $text .= str_repeat(' ', $largest + 2);

        return $text;
    }

    /**
     * Attempts to determine the width of the viewable CLI window.
     * 
     * @param int $default Optional default width (default: 80)
     * 
     * @return int Return terminal window width or default.
    */
    protected static final function getWidth(int $default = 80): int
    {
        if (static::$width === null) {
            self::calculateVisibleWindow();
        }

        return static::$width ?: $default;
    }

    /**
     * Attempts to determine the height of the viewable CLI window.
     * 
     * @param int $default Optional default height (default: 24)
     * 
     * @return int Return terminal window height or default.
    */
    protected static final function getHeight(int $default = 24): int
    {
        if (static::$height === null) {
            self::calculateVisibleWindow();
        }

        return static::$height ?: $default;
    }

    /**
     * Calculate the visible CLI window width and height.
     *
     * @return void
    */
    private static function calculateVisibleWindow(): void
    {
        if (static::$height !== null && static::$width !== null) {
            return;
        }

        if (is_platform('windows') && (getenv('TERM') || getenv('SHELL'))) {
            static::$height = (int) exec('tput lines');
            static::$width  = (int) exec('tput cols');
        } else {
            $size = exec('stty size');
            if (preg_match('/(\d+)\s+(\d+)/', $size, $matches)) {
                static::$height = (int) $matches[1];
                static::$width  = (int) $matches[2];
            }
        }

        if (static::$height === null || static::$width === null) {
            static::$height = (int) exec('tput lines');
            static::$width  = (int) exec('tput cols');
        }
    }

    /**
     * Get user input from the shell, after requesting for user to type or select an option.
     *
     * @param string|null $prompt You may specify a string to prompt the user after they have typed.
     * 
     * @return string User input string.
    */
    protected static final function input(?string $prompt = null): string
    {
        if (static::$isReadline && ENVIRONMENT !== 'testing') {
            return @readline($prompt);
        }

        echo $prompt;

        return fgets(fopen('php://stdin', 'rb'));
    }

    /**
     * Input validation on prompts
     *
     * @param string $value Input value
     * @param array $rules Validation rules
    */
    protected static final function validate(string $value, array $rules): bool
    {
        static $validation = null;
        $validation ??= new InputValidator();
        $validation->setRules($rules);
        $field = [
            'input' => $value
        ]; 

        if (!$validation->validate($field)) {
            static::error($validation->getError('input'));
            return false;
        }

        return true;
    }

    /**
     * Display error text on CLI 
     *
     * @param string $text Error message
     * @param string|null $foreground Foreground color name
     * @param string|null $background Optional background color name
     * 
     * @return void
    */
    public static final function error(string $text, string|null $foreground = 'white', ?string $background = 'red'): void
    {
        $stdout = static::$isColored;
        static::$isColored = static::isColorSupported(STDERR);
        $text = static::card($text);

        if ($foreground || $background) {
            $text = static::color($text, $foreground, $background);
        }

        static::fwrite($text . PHP_EOL, STDERR);
        static::$isColored = $stdout;
    }

    /**
     * Display success text on CLI 
     *
     * @param string $text Error message
     * @param string|null $foreground Foreground color name
     * @param string|null $background Optional background color name
     * 
     * @return void
    */
    public static final function success(string $text, string|null $foreground = 'white', ?string $background = 'green'): void
    {
        $stdout = static::$isColored;
        static::$isColored = static::isColorSupported(STDERR);
        $text = static::card($text);

        if ($foreground || $background) {
            $text = static::color($text, $foreground, $background);
        }

        static::fwrite($text . PHP_EOL, STDERR);
        static::$isColored = $stdout;
    }

    /**
     * Print text to CLI with newline.
     * 
     * @param string $text Text to display
     * @param string|null $foreground Optional foreground color name
     * @param string|null $background Optional background color name
     *
     * @return void
    */
    public static final function writeln(string $text = '', ?string $foreground = null, ?string $background = null): void
    {
        if ($foreground || $background) {
            $text = static::color($text, $foreground, $background);
        }

        if (!static::$isNewline) {
            $text = PHP_EOL . $text;
            static::$isNewline = true;
        }

        static::fwrite($text . PHP_EOL);
    }

    /**
     * Print text to CLI without a newline.
     * 
     * @param string $text Text to display
     * @param string|null $foreground Optional foreground color name
     * @param string|null $background Optional background color name
     *
     * @return void
    */
    public static final function write(string $text = '', ?string $foreground = null, ?string $background = null): void
    {
     
        if ($foreground || $background) {
            $text = static::color($text, $foreground, $background);
        }

        static::$isNewline = false;
        static::fwrite($text);
    }

    /**
     * Echo / output a message to CLI
     *
     * @param string $text string to output
     * @param string|null $foreground Optional foreground color name
     * @param string|null $background Optional background color name
     *
     * @return void
    */
    public static final function print(string $text, ?string $foreground = null, ?string $background = null): void
    {
        if ($foreground || $background) {
            $text = static::color($text, $foreground, $background);
        }

        echo $text;
    }

    /**
     * Write text to resource handler or output text if not in cli mode
     *
     * @param string $text string to output or write
     * @param resource $handle resource handler
     *
     * @return void
    */
    protected static final function fwrite(string $text, $handle = STDOUT): void
    {
        if (!is_command()) {
            echo $text;
            return;
        }

        fwrite($handle, $text);
    }

    /**
     * Clears the screen of output
     *
     * @return void
    */
    public static final function clear(): void
    {
        (is_platform('windows') && !static::streamSupports('sapi_windows_vt100_support', STDOUT))
            ? static::newLine(40)
            : static::fwrite("\033[H\033[2J");
    }

    /**
     * Clears cli output to update new text
     *
     * @return void
    */
    public static final function flush(): void
    {
        static::fwrite("\033[1A");
    }

    /**
     * Returns the given text with the correct color codes for a foreground and
     * optionally a background color.
     *
     * @param string $text Text to color
     * @param string|null $foreground Foreground color name
     * @param string|null $background Optional background color name
     * @param int|null $format Optionally apply text formatting.
     *
     * @return string A colored text if color is supported
    */
    public static final function color(string $text, string|null $foreground, ?string $background = null, ?int $format = null): string
    {
        if (!static::$isColored) {
            return $text;
        }

        return Colors::apply($text, $format, $foreground, $background);
    }

    /**
     * Create a new line 
     *
     * @param int $count Count of new lines to create
     * 
     * @return void 
    */
    public static final function newLine(int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            static::writeln();
        }
    }

    /**
     * Oops! Show an error message for unknow command.
     *
     * @param string $command The executed command.
     * @param string|null $color Text color for the command.
     * 
     * @return int Return status code for.
    */
    public static function oops(string $command, string|null $color = 'red'): int 
    {
       static::writeln('Unknown command ' . static::color("'$command'", $color) . ' not found', null);

       return STATUS_ERROR;
    }

    /**
     * Checks whether the current stream resource supports or
     * refers to a valid terminal type device.
     *
     * @param string $function Function name to check
     * @param resource|string $resource Resource to handle STDIN/STDOUT
     * 
     * @return bool if the stream resource is supported
    */
    public static final function streamSupports(string $function, mixed $resource): bool
    {
        if (ENVIRONMENT === 'testing') {
            return function_exists($function);
        }

        return function_exists($function) && @$function($resource);
    }

    /**
     * Register command line queries to static::$options 
     * To make available using getOptions() etc
     * 
     * @param array $values arguments 
     * 
     * @return void
     * @internal
    */
    public static final function explain(array $values): void
    {
        static::$commandsOptions = $values;
    }

    /**
     * Parse command line queries to static::$options
     * 
     * @param array $arguments arguments $_SERVER['argv']
     * 
     * @return array<string, mixed>
     * @internal Pass raw command arguments from $_SERVER['argv'].
    */
    public static final function parseCommands(array $arguments, bool $controller = false): array
    {
        $caller = $arguments[0] ?? '';
        $result = [
            'caller' => '',
            'command' => '',
            'group' => '',
            'arguments' => [],
            'options' => [],
        ];
        if ($caller === 'novakit' || $caller === 'php' || preg_match('/^.*\.php$/', $caller)) {
            array_shift($arguments); //Remove the front controller file
            $result['caller'] = implode(' ', $arguments);

            if($controller){
                $result['group'] = $arguments[0]??'';
                $result['command'] = $arguments[1]??'';
            }else{
                $result['command'] = $arguments[0]??'';
            }
        }else{
            $hasSpace = array_reduce($arguments, function ($carry, $item) {
                return $carry || strpos($item, ' ') !== false;
            }, false);

            $callerCommend = $arguments;
            if ($hasSpace) {
                $callerCommend = implode(' ', $arguments);
                $callerCommend = $callerCommend[0];
            }
            $result['caller'] = $callerCommend;
            $result['command'] = $arguments[1]??'';
        }

        // Unset command name 
        if($controller){
            unset($arguments[0], $arguments[1]);
        }else{
            unset($arguments[0]); 
        }

        $response = static::extract($arguments);
        $result['arguments'] = $response['arguments'];
        $result['options'] = $response['options'];

        return $result;
    }

    /**
     * Extract and process command line arguments.
     * 
     * @param array $arguments Command line arguments
     * @param bool $controller is the controller command?
     * 
     * @return array<string,array>
     * @internal
    */
    public static final function extract(array $arguments, $controller = false): array
    {
        $optionValue = false;
        $result = [
            'arguments' => [],
            'options' => [],
        ];
        foreach ($arguments as $i => $arg) {
            if ($arg[0] !== '-') {
                if ($optionValue) {
                    $optionValue = false;
                } else {
                    if($controller && strpos($arg, '=') !== false){
                        [$arg, $value] = explode('=', $arg);
                        $result['arguments'][] = $arg;
                        $result['arguments'][] = $value;
                    }else{
                        $result['arguments'][] = $arg;
                    }
                }
            } else {
                $arg = ltrim($arg, '-');
                $value = null;

                if(strpos($arg, '=') !== false){
                    [$arg, $value] = explode('=', $arg);
                }

                if (isset($arguments[$i + 1]) && $arguments[$i + 1][0] !== '-') {
                    $value = $arguments[$i + 1];
                    $optionValue = true;
                }

                $result['options'][$arg] = $value;
            }
        }

        return $result;
    }

    /**
     * Get command argument by index number
     * 
     * @param int $index Index position
     * 
     * @return mixed
    */
    public static final function getArgument(int $index): mixed
    {
        if(isset(static::$commandsOptions['arguments'][$index - 1])){
            return static::$commandsOptions['arguments'][$index - 1];
        }

        return null;
    }

    /**
     * Get command arguments
     * 
     * @return array
    */
    public static final function getArguments(): array
    {
        return static::$commandsOptions['arguments']??[];
    }

    /**
     * Get command name
     * 
     * @return string|null
    */
    public static final function getCommand(): ?string
    {
        return static::$commandsOptions['command'] ?? null;
    }

    /**
     * Get command caller command string.
     * The full passed command, options and arguments 
     * 
     * @return string|null
    */
    public static final function getCaller(): ?string
    {
        return static::$commandsOptions['caller'] ?? null;
    }

    /**
     * Get options value from command arguments.
     * If option flag is passed with an empty value true will be return else default or false
     * 
     * @param string $key Option key to retrieve
     * @param mixed $default Default value to return (default: false)
     * 
     * @return mixed Option ot default value.
     */
    public static final function getOption(string $key, mixed $default = false): mixed
    {
        $options = static::getOptions();

        if (array_key_exists($key, $options)) {
            return $options[$key] ?? true;
        }
    
        return $default;
    }

    /**
     * Returns the command controller class method.
     * 
     * @return string|null The command controller class method or null
    */
    public static final function getMethod(): string|null
    {
        return static::getQuery('classMethod');
    }

    /**
     * Returns the array of options.
     * 
     * @return array static::$options['options']
    */
    public static final function getOptions(): array
    {
        return static::$commandsOptions['options']??[];
    }

    /**
     * Gets a single query command-line by name.
     * If it doesn't exists return null
     *
     * @param string $name Option key name
     * 
     * @return mixed Command option query value.
    */
    public static final function getQuery(string $name): mixed
    {
        if(isset(static::$commandsOptions[$name])){
            return static::$commandsOptions[$name];
        }
        
        return null;
    }

    /**
     * Returns the raw array of requested query commands.
     * 
     * @return array static::$commandsOptions
    */
    public static final function getQueries(): array
    {
        return static::$commandsOptions;
    }

    /**
     * Check if the stream resource supports colors.
     *
     * @param resource|string $resource STDIN/STDOUT
     * 
     * @return bool Return true if the resource supports colors.
    */
    public static final function isColorSupported(mixed $resource = STDOUT): bool
    {
        if (self::isColorDisabled()) {
            return false;
        }

        if (is_platform('mac')) {
            return static::isMacTerminal();
        }

        if (is_platform('windows')) {
            return static::isWindowsTerminal($resource);
        }

        return static::streamSupports('stream_isatty', $resource);
    }

    /**
     * Checks whether the no color is available in environment
     *
     * @return bool 
    */
    private static function isColorDisabled(): bool
    {
        return isset($_SERVER['NO_COLOR']) || getenv('NO_COLOR') !== false;
    }

    /**
     * Checks whether the current terminal is mac terminal
     *
     * @return bool 
    */
    public static final function isMacTerminal(): bool
    {
        $termProgram = getenv('TERM_PROGRAM');
        return in_array($termProgram, ['Hyper', 'Apple_Terminal']) ||
            ($termProgram === 'iTerm' && version_compare(getenv('TERM_PROGRAM_VERSION'), '3.4', '>='));
    }

    /**
     * Checks whether the stream resource on windows is terminal
     *
     * @param resource|string $resource STDIN/STDOUT
     * 
     * @return bool return true if is windows terminal, false otherwise.
    */
    public static final function isWindowsTerminal(mixed $resource): bool
    {
        return static::streamSupports('sapi_windows_vt100_support', $resource) ||
            isset($_SERVER['ANSICON']) || getenv('ANSICON') !== false ||
            getenv('ConEmuANSI') === 'ON' ||
            getenv('TERM') === 'xterm';
    }

    /**
     * Checks whether framework has the requested command.
     *
     * @param string $command Command name to check
     * 
     * @return bool Return true if command exist, false otherwise.
    */
    public static final function hasCommand(string $command): bool
    {
        return Console::has($command);
    }

    /**
     * Checks whether system controller has requested command and run the command.
     *
     * @param string $command Command name to check
     * @param array $options Command compiled arguments
     * 
     * @return bool Return true if command exist, false otherwise.
     * @internal Used in router to execute controller command.
    */
    public static final function call(string $command, array $options): bool
    {
        static $terminal = null;

        if(Console::has($command)){
            $terminal ??= new static();
            $terminal->explain($options);

            $call = Console::execute($terminal, $options);

            return $call === STATUS_SUCCESS;
        }

        return false;
    }

    /**
     * Check if command is help command 
     * 
     * @param string $command Command name to check.
     * 
     * @return bool Return true if command is help, false otherwise.
    */
    public static final function isHelp(string $command): bool 
    {
        return preg_match('/^-{1,2}help/', $command);
    }

    /**
     * Print help
     *
     * @param array|null $helps Pass the command protected properties as an array.
     * @param bool $all Indicate whether you are printing all help commands or not
     *      - Used by system only
     * 
     * @return void
     * @internal Used in router to print controller help information.
    */
    public static final function helper(array|null $helps, bool $all = false): void
    {
        if( $helps === null){
            $helps = Commands::getCommands();
        }else{
            $helps = ($all ? $helps : [$helps]);
        }

        foreach($helps as $name => $help){

            foreach($help as $key => $value){
                if($key === 'description'){
                    static::writeln('Description:');
                    static::writeln(TextUtils::padStart('', 7) . $value);
                    static::newLine();
                }

                if($key === 'usages'){
                    static::writeln('Usages:');
                    if(is_array($value)){
                        foreach($value as $usage => $usages){
                            if(is_string($usage)){
                                static::writeln(TextUtils::padStart('', 7) . static::color($usage, 'yellow'));
                                static::writeln(TextUtils::padStart('', 10) . $usages);
                            }else{
                                static::writeln(TextUtils::padStart('', 7) . $usages);
                            }
                        }
                    }else{
                        static::writeln($value);
                    }
                    static::newLine();
                }

                if($key === 'options' && is_array($value)){
                    static::writeln('Options:');
                    foreach($value as $info => $option){
                        if(is_string($info)){
                            static::writeln(TextUtils::padStart('', 8) . static::color($info, 'lightGreen'));
                            static::writeln(TextUtils::padStart('', 11) . $option);
                        }else{
                            static::writeln(TextUtils::padStart('', 8) . $option);
                        }
                    }
                    static::newLine();
                }
            }
        }
    }

    /**
     * Print NovaKit Command line header information
     * 
     * @return void
    */
    public static final function header(): void
    {
        static::write(sprintf(
            'PHP Luminova v%s NovaKit Command Line Tool - Server Time: %s UTC%s',
            Foundation::NOVAKIT_VERSION,
            date('Y-m-d H:i:s'),
            date('P')
        ), 'green');
        static::newLine();
    }
}
