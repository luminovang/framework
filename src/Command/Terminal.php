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

use \Luminova\Command\Colors;
use \Luminova\Command\Executor;
use \Luminova\Command\TextUtils;
use \Luminova\Security\InputValidator;
use \Luminova\Exceptions\InvalidArgumentException;

class Terminal 
{
    /**
     * @var string $version command line tool version
    */
    public static string $version = '2.5.0';

    /**
     * @var int $version command line tool version code
    */
    public static int $versionCode = 250;

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
    public static $waitMessage = 'Press any key to continue...';

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
    protected static function waiting(int $seconds, bool $countdown = false): void
    {
        if ($seconds <= 0) {
            if (!$countdown) {
                static::writeln(static::$waitMessage);
                static::input();
            }
            return;
        }

        if ($countdown) {
            for ($time = $seconds; $time > 0; $time--) {
                static::fwrite("Waiting... ($time seconds) "  . PHP_EOL);
                static::wipeout();
                sleep(1);
            }
            static::wipeout();
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
    protected static function progress(int|bool $progressLine = 1, ?int $progressCount = 10, bool $beep = true): int|float
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
            static::wipeout();
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
     * @param int $progressCount Total count of progress bar to show
     * @param ?callable $onFinish Execute callback when progress finished
     * @param ?callable $onProgress Execute callback on each progress step
     * @param bool $beep Beep when progress is completed, default is true
     *
     * @return void
    */
    protected static function watcher(int $progressCount, ?callable $onFinish = null, ?callable $onProgress = null, bool $beep = true): void 
    {
        $progress = 0;
    
        for ($step = 1; $step <= $progressCount; $step++) {
            if ($progress < 100) {
                $progress = static::progress($step, $progressCount);
                if (is_callable($onProgress)) {
                    $onProgress($progress);
                }
            }
    
            usleep(100000); 
            if ($progress >= 100) {
                break;
            }
        }

        static::progress(false, null, $beep);
        if (is_callable($onFinish)) {
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
    protected static function beeps(int $num = 1): void
    {
        echo str_repeat("\x07", $num);
    }

    /**
     * Prompt for user for input.
     * Pass options as an array ["YES", "NO]
     * You can make a colored options by use the array key for color name ["green" => "YES","red" => "NO"]
     *
     * Examples
     *
     * @example $name = $this->prompt('What is your name?'); Prompt user to enter their name
     * @example $color = $this->prompt('Are you sure you want to continue?', ["green" => "YES","red" => "NO"]); Prompt user to choose any option and specify each option color in array key
     * @example $color = $this->prompt('What is your gender?', ['male','female']); Prompt user to select their gender, no colored text will be used
     * @example $email = $this->prompt('Are you sure you want to continue?', ["YES", "NO], 'required|in_array(YES,NO)'); Prompt user to choose any option and pass a validation
     *
     * @param string $message Prompt message
     * @param array $options  Options to prompt selection, 
     * @param string|null $validations Validation rules
     *
     * @return string The user input
    */
    protected static function prompt(string $message, array $options = [], string $validations = null, bool $silent = false): string
    {
        $default = '';
        $placeholder = '';
        $validationRules = false;
        $textOptions = [];
        
        if($options !== []){
            foreach($options as $color => $text){
                $textOptions[] = $text;
                $placeholder .= static::color($text, $color) . ',';
            }
            $placeholder = '[' . rtrim($placeholder, ',') . ']';
            $default = $textOptions[0];
        }

        $validationRules = $validations ?? $textOptions ?? false;
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
    protected static function chooser(string $text, array $options, bool $required = false): array
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
        static::writeOptions($optionValues, strlen($lastIndex));
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
        $input = static::getInputValues($inputArray, $optionValues);

        return $input;
    }

    /**
     * Return user selected options
     * Get Input Array Values
     * 
     * @param array $input user input as array
     * @param array $options options 
     * 
     * @return array<string|int, mixed> $options The selected array keys and values
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
     * @param array<string, array<string, mixed>> $options options 
     * 
     * @return void 
    */
    private static function writeOptions(array $options, int $max): void
    {
        foreach ($options as $key => $value) {
            $name = str_pad('  [' . $key . ']  ', $max, ' ');
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
    public static function wrap(?string $string = null, int $max = 0, int $leftPadding = 0): string
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
     * Attempts to determine the width of the viewable CLI window.
     * 
     * @return int static::$width or fallback to default
    */
    protected static function getWidth(int $default = 80): int
    {
        if (static::$width === null) {
            static::getVisibleWindow();
        }

        return static::$width ?: $default;
    }

    /**
     * Attempts to determine the height of the viewable CLI window.
     * 
     * @return int static::$height or fallback to default
    */
    protected static function getHeight(int $default = 24): int
    {
        if (static::$height === null) {
            static::getVisibleWindow();
        }

        return static::$height ?: $default;
    }

    /**
     * Get the visible CLI width and height.
     *
     * @return void
    */
    protected static function getVisibleWindow(): void
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
     * Get input from the shell, using readline or the standard STDIN
     *
     * Named options must be in the following formats:
     * php index.php user -v --v -name=John --name=John
     *
     * @param string|null $prefix You may specify a string with which to prompt the user.
    */
    protected static function input(?string $prefix = null): string
    {
        if (static::$isReadline && ENVIRONMENT !== 'testing') {
            return @readline($prefix);
        }

        echo $prefix;

        return fgets(fopen('php://stdin', 'rb'));
    }

    /**
     * Input validation on prompts
     *
     * @param string $value Input value
     * @param array $rules Validation rules
    */
    protected static function validate(string $value, array $rules): bool
    {
        $validation = new InputValidator();
        $validation->setRules($rules);
        $field = [
            'input' => $value
        ]; 

        if (!$validation->validateEntries($field)) {
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
    public static function error(string $text, string|null $foreground = 'red', ?string $background = null): void
    {
        $stdout = static::$isColored;
        static::$isColored = static::isColorSupported(STDERR);

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
    public static function writeln(string $text = '', ?string $foreground = null, ?string $background = null)
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
    public static function write(string $text = '', ?string $foreground = null, ?string $background = null): void
    {
     
        if ($foreground || $background) {
            $text = static::color($text, $foreground, $background);
        }

        static::$isNewline = false;
        static::fwrite($text);
    }

    /**
     * Echo / output text if
     *
     * @param string $text string to output
     * @param string|null $foreground Optional foreground color name
     * @param string|null $background Optional background color name
     *
     * @return void
    */
    public static function print(string $text, ?string $foreground = null, ?string $background = null): void
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
    protected static function fwrite(string $text, $handle = STDOUT): void
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
    public static function clear(): void
    {
        is_platform('windows') && !static::streamSupports('sapi_windows_vt100_support', STDOUT)
            ? static::newLine(40)
            : static::fwrite("\033[H\033[2J");
    }

    /**
     * Clears cli output to update new text
     *
     * @return void
    */
    public static function wipeout(): void
    {
        static::fwrite("\033[1A");
    }

    /**
     * Returns the given text with the correct color codes for a foreground and
     * optionally a background color.
     *
     * @param string $text Text to color
     * @param string $foreground Foreground color name
     * @param string|null $background Optional background color name
     * @param int|null $format Optionally apply text formatting.
     *
     * @return string A colored text if color is supported
    */
    public static function color(string $text, string $foreground, ?string $background = null, ?int $format = null): string
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
    public static function newLine(int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            static::writeln();
        }
    }

    /**
     * Checks whether the current stream resource supports or
     * refers to a valid terminal type device.
     *
     * @param string $function Function name to check
     * @param resource $resource Resource to handle STDIN/STDOUT
     * 
     * @return bool if the stream resource is supported
    */
    public static function streamSupports(string $function, $resource): bool
    {
        if (ENVIRONMENT === 'testing') {
            return function_exists($function);
        }

        return function_exists($function) && @$function($resource);
    }

    /**
     * Register command line queries to static::$options and run it
     * This method is being called in router to parse commands
     * 
     * @param array $values arguments 
     * @param bool $run run command after it has been registered 
     * 
     * @return int
    */
    public static function registerCommands(array $values, bool $run = true): int
    {
        static::explain($values);

        if($run){
            $argument = static::getArgument(1);

            if($argument === 'help'){
                return STATUS_ERROR;
            }
            
            if($argument === 'list'){
                return STATUS_ERROR;
            }
        }

        return STATUS_SUCCESS;
    }

    /**
     * Register command line queries to static::$options 
     * To make available using getOptions() etc
     * 
     * @param array $values arguments 
     * 
     * @return void
    */
    public static function explain(array $values): void
    {
        static::$commandsOptions = $values;
    }

    /**
     * Parse command line queries to static::$options
     * 
     * @param array $arguments arguments $_SERVER['argv']
     * 
     * @return array<string, mixed>
    */
    public static function parseCommands(array $arguments): array
    {
        $optionValue = false;
        $caller = $arguments[0] ?? '';
        $result = [
            'caller' => '',
            'command' => '',
            'arguments' => [],
            'options' => [],
        ];
        if ($caller === 'novakit' || $caller === 'php' || preg_match('/^.*\.php$/', $caller)) {
            array_shift($arguments); //Remove the front controller file
            $result['caller'] = implode(' ', $arguments);
            $result['command'] = $arguments[0]??'';
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

        unset($arguments[0]); // Unset command name 
        
        foreach ($arguments as $i => $arg) {
            if ($arg[0] !== '-') {
                if ($optionValue) {
                    $optionValue = false;
                } else {
                    $result['arguments'][] = $arg;
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
     * Get the current command controller views
     * @return array $views
    */
    public static function getRequestCommands(): array
    {
        $views = [
            'view' => '',
            'options' => [],
        ];
       
        if (is_command() && isset($_SERVER['argv'][1])) {
            $viewArgs = array_slice($_SERVER['argv'], 1);
            $view = '/';
            $options = [];

            foreach ($viewArgs as $arg) {
                if (strpos($arg, '-') === 0) {
                    $options[] = $arg;
                } else {
                    $view .= $arg . '/';
                }
            }

            $view = rtrim($view, '/');
            $views['view'] = $view;
            $views['options'] = $options;
        }

        return $views;
    }

    /**
     * Get command argument by index number
     * 
     * @param int $index Index postion
     * 
     * @return string|null|int
    */
    public static function getArgument(int $index): mixed
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
    public static function getArguments(): array
    {
        return static::$commandsOptions['arguments']??[];
    }

    /**
     * Get command name
     * 
     * @return string|null
    */
    public static function getCommand(): ?string
    {
        return static::$commandsOptions['command'] ?? null;
    }

    /**
     * Get command caller command string
     * The full passed command, options and arguments 
     * 
     * @return string|null
    */
    public static function getCaller(): ?string
    {
        return static::$commandsOptions['caller'] ?? null;
    }

    /**
     * Get options value 
     * If option flag is passed with an empty value true will be return else false
     * 
     * @param string $key Option key to retrieve
     * @param string $default Default value to return (default: false)
     * 
     * @return null|string|int|bool
     */
    public static function getOption(string $key, mixed $default = false): mixed
    {
        $options = static::getOptions();

        if (array_key_exists($key, $options)) {
            return $options[$key] ?? true;
        }
    
        return $default;
    }

    /**
     * Returns the array of options.
     * 
     * @return array static::$options['options']
    */
    public static function getOptions(): array
    {
        return static::$commandsOptions['options']??[];
    }

    /**
     * Gets a single query command-line by name.
     * If it doesn't exists return null
     *
     * @param string $name Option key name
     * 
     * @return string|array|null
    */
    public static function getQuery(string $name): mixed
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
    public static function getQueries(): array
    {
        return static::$commandsOptions;
    }

    /**
     * Check if the stream resource supports colors.
     *
     * @param resource $resource STDIN/STDOUT
     * @return bool 
    */
    public static function isColorSupported($resource): bool
    {
        if (static::isColorDisabled()) {
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
    public static function isMacTerminal(): bool
    {
        $termProgram = getenv('TERM_PROGRAM');
        return in_array($termProgram, ['Hyper', 'Apple_Terminal']) ||
            ($termProgram === 'iTerm' && version_compare(getenv('TERM_PROGRAM_VERSION'), '3.4', '>='));
    }

    /**
     * Checks whether the stream resource on windows is terminal
     *
     * @param resource $resource STDIN/STDOUT
     * 
     * @return bool 
    */
    public static function isWindowsTerminal($resource): bool
    {
        return static::streamSupports('sapi_windows_vt100_support', $resource) ||
            isset($_SERVER['ANSICON']) || getenv('ANSICON') !== false ||
            getenv('ConEmuANSI') === 'ON' ||
            getenv('TERM') === 'xterm';
    }

    /**
     * Checks whether system has requested command
     *
     * @param string $command
     * @param array $options
     * 
     * @return bool
    */
    public static function hasCommand(string $command, array $options): bool
    {
        static $terminal = null;
        if(Executor::has($command)){
            $terminal ??= new static();
            
            $terminal->registerCommands($options);
            Executor::call($terminal, $options);

            return true;
        }

        return false;
    }

    /**
     * Print help
     *
     * @param array $help
     * 
     * @return void
    */
    public static function printHelp(array $help): void
    {
        foreach($help as $key => $value){
            if($key === 'usage'){
                static::writeln('Usages:');
                if(is_array($value)){
                    static::newLine();
                    foreach($value as $usages){
                        static::writeln(TextUtils::leftPad('', 7) . $usages);
                    }
                }else{
                    static::writeln($value);
                }
            }elseif($key === 'description'){
                static::writeln('Description:');
                static::newLine();
                static::writeln($value);
            }elseif($key === 'options' && is_array($value)){
                static::writeln('Options:');
                static::newLine();
                foreach($value as $info => $option){
                    //static::writeln(TextUtils::leftPad('', 8) . static::color($info, 'lightGreen') . TextUtils::leftPad('', 10) . $option);
                    static::writeln(TextUtils::leftPad('', 8) . static::color($info, 'lightGreen'));
                    static::writeln(TextUtils::leftPad('', 15) . $option);
                }
            }
            static::newLine();
        }
    }

    /**
     * Gets request status code [1, 0]
     * @param void|bool|null|int $result response from callback function
     * @return int
    */
    public static function getStatusCode(mixed $result = null): int
    {
        if ($result === false || (is_int($result) && $result == STATUS_ERROR)) {
            return STATUS_ERROR;
        }

        return STATUS_SUCCESS;
    }

    /**
     * Print NovaKit Command line header information
     * 
     * @return void
    */
    public static function header(): void
    {
        static::write(sprintf(
            'PHP Luminova v%s NovaKit Command Line Tool - Server Time: %s UTC%s',
            static::$version,
            date('Y-m-d H:i:s'),
            date('P')
        ), 'green');
        static::newLine();
    }

    /**
     * Get the PHP script path.
     *
     * @return string
    */
    public static function phpScript(): string 
    {
        return PHP_BINARY;
    }
}
