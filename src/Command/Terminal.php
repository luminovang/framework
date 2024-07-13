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
    protected static ?int $height = null;

    /**
     * Width of terminal visible window
     *
     * @var int|null $width
    */
    protected static ?int $width = null;

    /**
     * Is the readline library on the system.
     *
     * @var bool $isReadline
     */
    public static bool $isReadline = false;

    /**
     * Write in a new line enabled.
     *
     * @var bool $isNewline
    */
    protected static bool $isNewline = false;

    /**
     * Is colored text supported.
     *
     * @var bool $isColored
    */
    protected static bool $isColored = false;

    /**
     * Passed command line arguments and infos about command.
     *
     * @var array $commandsOptions
    */
    protected static array $commandsOptions = [];

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
     * Show a waiting countdown, intentionally freeze screen while waiting.
     * Or ask user for a key press to continue.
     *
     * @param int  $seconds Number of seconds for waiting.
     * @param bool $countdown Show waiting countdown.
     * @param string $message Waiting message instruction.
     *
     * @return void
     * 
     * Examples:
     * 
     * @example $this->waiting(20, true); show waiting for 20 seconds with countdown message.
     * @example $this->waiting(0, false); show waiting message till user press any key.
     * @example $this->waiting(20, false); show waiting for 20 seconds with a freezed screen.
     */
    protected static final function waiting(
        int $seconds, 
        bool $countdown = false, 
        string $message = 'Press any key to continue...'
    ): void
    {
        if ($seconds <= 0) {
            if (!$countdown) {
                static::writeln($message);
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
     * Displays a progress bar on the CLI, this method should be called in a loop.
     *
     * @param int|bool $progressLine Current loop index number or false to terminate the progress bar.
     * @param int|null $progressCount Total count of progress bar to show or null to on termination.
     * @param bool $beep Weather to beep when progress is completed (default: true).
     *
     * @return float|int Return the progress step percentage.
     * 
     * Examples:
     * 
     * @example $this->progress(1, 10, true); Show progress bar line with beep when completed.
     * @example $this->progress(1, 10, false); Show progress bar line without beep when completed.
    */
    protected static final function progress(int|bool $progressLine = 1, ?int $progressCount = 10, bool $beep = true): int|float
    {
        if ($progressLine === false || $progressCount === null) {
            if($beep){
                static::beeps(1);
            }
            return 100;
        }

        $progressLine = max(0, $progressLine);
        $progressCount = max(1, $progressCount);
        $percent = min(100, max(0, ($progressLine / $progressCount) * 100));
        $barWidth = (int) round($percent / 10);
        $progressBar = '[' . str_repeat('#', $barWidth) . str_repeat('.', 10 - $barWidth) . ']';
        $progressText = sprintf(' %3d%% Complete', $percent);

        static::fwrite("\033[32m" . $progressBar . "\033[0m" . $progressText . PHP_EOL);

        if ($progressLine <= $progressCount) {
            static::clear();
        }

        return $percent;
    }

    /**
     * Displays a progress bar on the CLI with an optional callback functions.
     * This method shouldn't be called in a loop, pass your function to execute in `$stepCallback` Closure function.
     * 
     * This is useful when you just want to display a progress bar and execute next method when it finish counting.
     *
     * @param int $limit Total count of progress bar to show.
     * @param Closure|null $onFinish(): void Execute callback when progress finished.
     * @param Closure|null $onProgress(int $progress):void Execute callback on each progress step.
     * @param bool $beep Weather to beep when progress is completed (default: true).
     *
     * @return void
     * 
     * Examples:
     * 
     * @example $this->watcher(100, Closure, Closure, true) Show 100 lines of progress bar with a callbacks and beep on finish.
    */
    protected static final function watcher(
        int $limit, 
        ?Closure $onFinish = null, 
        ?Closure $onProgress = null, 
        bool $beep = true
    ): void 
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
     * Beep or make a bell sound for a certain number of time.
     * 
     * @param int $total The total number of time to beep.
     *
     * @return void
    */
    public static final function beeps(int $total = 1): void
    {
        echo str_repeat("\x07", $total);
    }

    /**
     * Prompt user to type something, optionally pass an array of options for user to enter any.
     * Optionally, you can make a colored options by using the array key for color name ["green" => "YES","red" => "NO"].
     *
     *
     * @param string $message The message to prompt.
     * @param array $options  Optional array options to prompt for selection.
     * @param string|null $validations Optional validation rules to ensure only the listed options are allowed.
     * @param bool $silent Weather to print validation failure message if wrong option was selected (default: false).
     *
     * @return string Return user input value.
     * 
     * Examples
     *
     * @example $name = $this->prompt('What is your name?'); Prompt user to enter their name.
     * @example $color = $this->prompt('Are you sure you want to continue?', ["green" => "YES","red" => "NO"]); Prompt user to choose any option and specify each option color in array key.
     * @example $color = $this->prompt('What is your gender?', ['male','female']); Prompt user to select their gender, no colored text will be used.
     * @example $email = $this->prompt('Are you sure you want to continue?', ["YES", "NO], 'required|in_array(YES,NO)'); Prompt user to choose any option and pass a validation.
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
                    static::fwrite('Input validation failed. ');
                }
                static::fwrite($message . ' ' . $placeholder . ': ');
            }
            $input = static::input();
            $input = ($input === '') ? $default : $input;
        } while ($validationRules !== false && !static::validate($input, ['input' => $validationRules]));
    

        return $input;
    }

    /**
     * Prompts the user to enter a password, with options for retry attempts and a timeout.
     *
     * @param string $message The message to display when prompting for the password.
     * @param int $retry The number of retry attempts if the user provides an empty password (default: 3).
     * @param int $timeout The number of seconds to wait for user input before displaying an error message (default: 0).
     *
     * @return string Return the entered password.
     */
    protected static final function password(
        string $message = 'Enter Password', 
        int $retry = 3, 
        int $timeout = 0
    ): string
    {
        $attempts = 0;
        $visibilityPromptShown = false;

        do {
            $password = '';

            if (is_platform('windows') || static::isWindowsTerminal(STDIN)) {
                $vbscript = sys_get_temp_dir() . 'prompt_password.vbs';
                $inputBox = 'wscript.echo(InputBox("'. addslashes($message) . '", "", ""))';
           
                if ($timeout > 0) {
                    $result = static::timeout(static function() {
                        static::newLine();
                        static::error("Error: Timeout exceeded. No input provided.");
                    }, $timeout);
    
                    if ($result === true) {
                        return '';
                    }
                }
    
                if (file_put_contents($vbscript, $inputBox) !== false) {
                    $password = shell_exec("cscript //nologo " . escapeshellarg($vbscript));
                }
    
                if (empty($password)) {
                    $password = shell_exec('powershell -Command "Read-Host -AsSecureString | ConvertFrom-SecureString"');
                }

                $password = ($password === false || $password === null) ? '' : trim($password);
    
                unlink($vbscript);
            } else {
                $command = "/usr/bin/env bash -c 'echo OK'";
                $continue = shell_exec($command);
                $continue = ($continue === false || $continue === null) ? 'ERR' : trim($continue);
    
                if ($continue !== 'OK') {
                    if (!$visibilityPromptShown && static::visibility(false) === false) {
                        $continue = static::prompt('Your password may be visible while typing, do you wish to continue?', [
                            'yes', 'no'
                        ], 'required|in_array(yes,no)');
                    } else {
                        $continue = 'yes';
                    }
                }
    
                if ($continue !== 'no') {
                    static::fwrite($message . ': ');
    
                    if ($continue === 'yes') {
                        static::visibility(false);
                    }
    
                    if ($timeout > 0) {
                        $result = static::timeout(static function() {
                            static::newLine();
                            static::error("Error: Timeout exceeded. No input provided.");
                        }, $timeout);
    
                        if ($result === true) {
                            if ($continue === 'yes') {
                                static::visibility(true);
                            }
                            
                            return '';
                        }
                    }
    
                    if ($continue === 'yes') {
                        $password = static::input();
                        static::visibility(true);
                    } elseif ($continue === 'OK') {
                        $command = "/usr/bin/env bash -c 'read -s inputPassword && echo \$inputPassword'";
                        $password = shell_exec($command);
                        $password = ($password === false || $password === null) ? '' : trim($password);
                    }
                }
            }
    
            if ($password !== '') {
                static::newLine();
                return $password;
            }
    
            $attempts++;
            $visibilityPromptShown = true;
            if ($retry === 0 || $attempts < $retry) {
                static::newLine();
                static::error("Error: Password cannot be empty. Please try again.");
            }
        } while ($retry === 0 || $attempts < $retry);
    
        if($retry !== 0){
            static::newLine();
            static::error("Error: Maximum retry attempts reached. Exiting.");
        }

        return '';
    }

    /**
     * Execute a callback function after a specified timeout when no input or output is received.
     *
     * @param Closure $callback The callback function to execute on timeout.
     * @param int $timeout Timeout duration in seconds. If <= 0, callback is invoked immediately (default: 0).
     * @param mixed $stream Optional stream to monitor for activity (default: STDIN).
     * 
     * @return bool Returns true if the timeout occurred and callback was executed, otherwise false.
     */
    protected static final function timeout(Closure $callback, int $timeout = 0, mixed $stream = STDIN): bool
    {
        if ($timeout <= 0) {
            $callback();
            return true;
        }
    
        $read = [$stream];
        $write = null;
        $except = null;
        $result = stream_select($read, $write, $except, $timeout);
        
        if ($result === false || $result === 0) {
            $callback();
            return true;
        }
    
        return false;
    }

    /**
     * Toggles the terminal visibility of user input.
     *
     * @param bool $visibility True to show input, False to hide input.
     * 
     * @return bool Return true if visibility toggling was successful, false otherwise.
     */
    protected static final function visibility(bool $visibility = true): bool
    {
        $command = ($visibility === true) ? 'stty echo' : 'stty -echo';
        
        return shell_exec($command) !== null;
    }

    /**
     * Prompt user with multiple selection options.
     * Display array index key as the option identifier to select.
     * If you use associative array users will still see index key instead.
     *
     *
     * @param string $text  Display text description for your multiple options.
     * @param array  $options A list of options ['male' => 'Male', 'female' => 'Female] or ['male', 'female'].
     * @param bool $required Require user to choose any option else the first array will be return as default.
     *
     * @return array<string|int,mixed> $options The selected array keys and values.
     * @throws InvalidArgumentException Throw if options is an empty array.
     * 
     * Examples:
     *
     * @example $array = $this->chooser('Choose your programming languages?', ['PHP', 'JAVA', 'SWIFT', 'JS', 'SQL', 'CSS', 'HTML']); Prompt multiple chooser, using PHP as default if user didn't select anything before hit return.
     * @example $array = $this->chooser('Choose your programming languages?', ['PHP', 'JAVA', 'SWIFT', 'JS', 'SQL', 'CSS', 'HTML'], true); Prompt multiple chooser, persisting that user must choose an option.
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

            $input = static::input();
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
     * @return array|int The output of the command as an array of lines, or false on failure.
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
     * Get user multiple selected options from input.
     * 
     * @param array $input The user input array.
     * @param array $options The prompted options.
     * 
     * @return array<string|int,mixed> $options The selected array keys and values.
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
     * Display select options with key index as an identifier.
     * 
     * @param array<string,mixed> $options The options to display.
     * @param int $max The maximum padding end to apply.
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
     * Wrap a text with padding left and width to a maximum number.
     * 
     * @param string|null $text The text to wrap.
     * @param int $max The maximum width to use.
     * @param int $leftPadding The left padding to apply.
     * 
     * @return string Return wrapped text with padding left and width.
    */
    public static final function wrap(?string $text = null, int $max = 0, int $leftPadding = 0): string
    {
        if ($text === null || $text === '') {
            return '';
        }
        $max = min($max, static::getWidth());
        $max -= $leftPadding;

        $lines = wordwrap($text, $max, PHP_EOL);

        if ($leftPadding > 0) {
            $lines = preg_replace('/^/m', str_repeat(' ', $leftPadding), $lines);
        }

        return $lines;
    }

    /**
     * Generate a card like with text centered within the card.
     *
     * @param string $text The text display in card.
     * @param int|null $padding Optional maximum padding to use.
     * 
     * @return string Return beautiful card with text.
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
     * @param int $default Optional default width (default: 80).
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
     * @param int $default Optional default height (default: 24).
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
     * @param string|null $prompt Optional message to prompt the user after they have typed.
     * @param bool $useFopen Optional use file-read, this opens `STDIN` stream in read-only binary mode (default: false). 
     *                      This creates a new file resource.
     * 
     * @return string Return user input string.
    */
    protected static final function input(?string $prompt = null, bool $useFopen = false): string
    {
        if (static::$isReadline && ENVIRONMENT !== 'testing') {
            return @readline($prompt);
        }

        if ($prompt !== null) {
            echo $prompt;
        }

        if ($useFopen) {
            $input  = fgets(fopen(STDIN, 'rb'));
        } else {
            $input = fgets(STDIN);
        }

        return ($input === false) ? '' : trim($input);
    }

    /**
     * Command user input validation on prompts.
     *
     * @param string $value The user input value.
     * @param array $rules The validation rules.
     * 
     * @return bool Return true if validation succeeded, false if validation failed.
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
     * Display error text on CLI .
     *
     * @param string $text The text to output.
     * @param string|null $foreground Foreground color name.
     * @param string|null $background Optional background color name.
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
     * Display success text on CLI.
     *
     * @param string $text The text to output.
     * @param string|null $foreground Foreground color name.
     * @param string|null $background Optional background color name.
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
     * @param string $text The text to write.
     * @param string|null $foreground Optional foreground color name.
     * @param string|null $background Optional background color name.
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
     * @param string $text The text to write.
     * @param string|null $foreground Optional foreground color name.
     * @param string|null $background Optional background color name.
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
     * Print a message to CLI using echo.
     *
     * @param string $text The text to print.
     * @param string|null $foreground Optional foreground color name.
     * @param string|null $background Optional background color name.
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
     * Write text to resource handler or output text if not in cli mode.
     *
     * @param string $text The text to output or write.
     * @param resource $handle The resource handler to use (e.g. STDOUT, STDIN, STDERR).
     *
     * @return void
    */
    protected static final function fwrite(string $text, mixed $handle = STDOUT): void
    {
        if (!is_command()) {
            echo $text;
            return;
        }

        fwrite($handle, $text);
    }

    /**
     * Clears the screen of output.
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
     * Clears cli output to update new text.
     *
     * @return void
    */
    public static final function flush(): void
    {
        static::fwrite("\033[1A");
    }

    /**
     * Returns the given text with the correct color codes for a foreground and optionally a background color.
     *
     * @param string $text The text to apply color to.
     * @param string|null $foreground The foreground color name.
     * @param string|null $background Optional background color name.
     * @param int|null $format Optionally apply text formatting style.
     *
     * @return string A colored text if color is supported
    */
    public static final function color(
        string $text, 
        string|null $foreground, 
        ?string $background = null, 
        ?int $format = null
    ): string
    {
        if (!static::$isColored) {
            return $text;
        }

        return Colors::apply($text, $format, $foreground, $background);
    }

    /**
     * Create and print new lines based on count passed.
     *
     * @param int $count The count of new lines to print.
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
     * Oops! Show an error message for unknown command.
     *
     * @param string $command The executed command.
     * @param string|null $color Text color for the command.
     * 
     * @return int Return status code STATUS_ERROR.
    */
    public static function oops(string $command, string|null $color = 'red'): int 
    {
       static::writeln('Unknown command ' . static::color("'$command'", $color) . ' not found', null);

       return STATUS_ERROR;
    }

    /**
     * Prints a formatted table header and rows to the console.
     *
     * @param array<int,string> $headers The headers for the table columns.
     * @param array<string,string> $rows The rows of data to display in the table.
     * @param string|null $headerColor The table heading columns text color.
     * @param int $headerPadding Optional table heading columns padding.
     * 
     * @return void
     */
    public static function table(
        array $headers, 
        array $rows, 
        ?string $headerColor = null, 
        int $headerPadding = 1
    ): void
    {
        $border = '+';
        $colorLength = 0;

        if($headerColor !== null){
            $colorLength = Colors::length(null, $headerColor, null) + $headerPadding;
        }

        $columnWidths = array_map(function($header) use ($rows) {
            $columnValues = array_column($rows, $header);
            $maxValueLength = max(array_map('strlen', $columnValues));
            return max(strlen($header), $maxValueLength);
        }, $headers);

        foreach ($columnWidths as $width) {
            $border .= str_repeat('-', $width + 2) . '+';
        }

        echo $border . PHP_EOL;

        $headerRow = '|';
        foreach ($headers as $i => $header) {
            if($headerColor === null){
                $headerRow .= ' ' . str_pad($header, $columnWidths[$i]) . ' |';
            }else{
                $headerRow .= ' ' . str_pad(self::color($header, $headerColor), $columnWidths[$i] + $colorLength) . ' |';
            }

        }
        echo $headerRow . PHP_EOL;
        echo $border . PHP_EOL;

        foreach ($rows as $row) {
            $rowString = '|';
            foreach ($headers as $i => $header) {
                $rowString .= ' ' . str_pad($row[$header], $columnWidths[$i]) . ' |';
            }
            echo $rowString . PHP_EOL;
        }

        echo $border . PHP_EOL;
    }

    /**
     * Checks whether the current stream resource supports or refers to a valid terminal type device.
     *
     * @param string $function Function name to check.
     * @param resource|string $resource Resource to handle (e.g. STDIN, STDOUT).
     * 
     * @return bool Return true if stream resource is supported, otherwise false.
    */
    public static final function streamSupports(string $function, mixed $resource): bool
    {
        if (ENVIRONMENT === 'testing') {
            return function_exists($function);
        }

        return function_exists($function) && @$function($resource);
    }

    /**
     * Register command line queries to make it available using getOptions() etc.
     * 
     * @param array $values The command arguments.
     * 
     * @return void
     * @internal
    */
    public static final function explain(array $values): void
    {
        static::$commandsOptions = $values;
    }

    /**
     * Parse command line queries.
     * 
     * @param array $arguments The command arguments from $_SERVER['argv'].
     * 
     * @return array<string,mixed> Return parsed command arguments and options.
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
     * @param bool $controller is the controller command?.
     * 
     * @return array<string,array> Return extracted command line arguments and options.
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
     * Get command argument by index number.
     * 
     * @param int $index The index position to get.
     * 
     * @return mixed Return command argument by index number.
    */
    public static final function getArgument(int $index): mixed
    {
        if(isset(static::$commandsOptions['arguments'][$index - 1])){
            return static::$commandsOptions['arguments'][$index - 1];
        }

        return null;
    }

    /**
     * Get command arguments.
     * 
     * @return array Return command arguments.
    */
    public static final function getArguments(): array
    {
        return static::$commandsOptions['arguments']??[];
    }

    /**
     * Get command name.
     * 
     * @return string|null Return the command name.
    */
    public static final function getCommand(): ?string
    {
        return static::$commandsOptions['command'] ?? null;
    }

    /**
     * Get command caller command string.
     * 
     * @return string|null Return the full passed command, options and arguments.
    */
    public static final function getCaller(): ?string
    {
        return static::$commandsOptions['caller'] ?? null;
    }

    /**
     * Get options value from command arguments.
     * If option key is passed with an empty value true will be return otherwise the default value.
     * 
     * @param string $key Option key to retrieve.
     * @param mixed $default Default value to return (default: false).
     * 
     * @return mixed Return option value, true if empty value, otherwise default value.
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
     * Get options value from command arguments with an alias key to lookup if main key isn't found.
     * If option key is passed with an empty value true will be return otherwise the default value.
     * 
     * @param string $key Option key to retrieve.
     * @param string $alias Option key alias to retrieve. if main key is not found.
     * @param mixed $default Default value to return (default: false).
     * 
     * @return mixed Return option value, true if empty value, otherwise default value.
     */
    public static final function getAnyOption(string $key, string $alias, mixed $default = false): mixed
    {
        $options = static::getOptions();

        if (array_key_exists($key, $options)) {
            return $options[$key] ?? true;
        }

        if (array_key_exists($alias, $options)) {
            return $options[$alias] ?? true;
        }
    
        return $default;
    }

    /**
     * Returns the command controller class method.
     * 
     * @return string|null The command controller class method or null.
    */
    public static final function getMethod(): string|null
    {
        return static::getQuery('classMethod');
    }

    /**
     * Returns the array of options.
     * 
     * @return array static::$options['options'].
    */
    public static final function getOptions(): array
    {
        return static::$commandsOptions['options']??[];
    }

    /**
     * Gets a single query command-line by name, if it doesn't exists return null.
     *
     * @param string $name Option key name.
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
     * @return array static::$commandsOptions.
    */
    public static final function getQueries(): array
    {
        return static::$commandsOptions;
    }

    /**
     * Check if the stream resource supports colors.
     *
     * @param resource|string $resource STDIN/STDOUT.
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
     * Checks whether the no color is available in environment.
     *
     * @return bool Return true if color is disabled, false otherwise.
    */
    private static function isColorDisabled(): bool
    {
        return isset($_SERVER['NO_COLOR']) || getenv('NO_COLOR') !== false;
    }

    /**
     * Checks whether the current terminal is mac terminal.
     *
     * @return bool Return true if is mac, otherwise false.
    */
    public static final function isMacTerminal(): bool
    {
        $termProgram = getenv('TERM_PROGRAM');
        return in_array($termProgram, ['Hyper', 'Apple_Terminal']) ||
            ($termProgram === 'iTerm' && version_compare(getenv('TERM_PROGRAM_VERSION'), '3.4', '>='));
    }

    /**
     * Checks whether the stream resource on windows is terminal.
     *
     * @param resource|string $resource The resource type to check (e.g. STDIN, STDOUT).
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
     * @param string $command Command name to check.
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
     * @param string $command Command name to check.
     * @param array $options Command compiled arguments.
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
     * Check if command is help command.
     * 
     * @param string|array $command Command name to check or command array options.
     * 
     * @return bool Return true if command is help, false otherwise.
    */
    public static final function isHelp(string|array $command): bool 
    {
        if(is_array($command)){
            $command = $command['options'] ?? $command;

            if(array_key_exists('help', $command) || array_key_exists('h', $command)){
                return true;
            }

            return false;
        }

        return preg_match('/^(-h|--help|)$/', $command);
    }

    /**
     * Print help Used by system only.
     *
     * @param array|null $helps Pass the command protected properties as an array.
     * @param bool $all Indicate whether you are printing all help commands or not.
     * 
     * @return void
     * @internal Used in router to print controller help information.
    */
    public static final function helper(array|null $helps, bool $all = false): void
    {
        $helps = (($helps === null) ? Commands::getCommands() : ($all ? $helps : [$helps]));

        foreach($helps as $name => $help){
            foreach($help as $key => $value){
                if($key === 'description'){
                    static::writeln('Description:');
                    static::writeln($value);
                    static::newLine();
                }

                if($key === 'usages'){
                    if(is_array($value)){
                        static::addHelp($value, $key);
                    }else{
                        static::writeln('Usages:');
                        static::writeln($value);
                        static::newLine();
                    }
                }

                if(is_array($value)){
                    if($key === 'options'){
                        static::addHelp($value, $key);
                    }

                    if($key === 'examples'){
                        static::addHelp($value, $key);
                    }
                }
            }
        }
    }

     /**
     * Print NovaKit Command line header information.
     * 
     * @return void
    */
    public static final function header(): void
    {
        static::write(sprintf(
            'PHP Luminova v%s NovaKit Command Line Tool v%s - Server Time: %s UTC%s',
            Foundation::VERSION,
            Foundation::NOVAKIT_VERSION,
            date('Y-m-d H:i:s'),
            date('P')
        ), 'green');
        static::newLine();
    }

    /**
     * Add help information.
     * 
     * @param array $option Help line options.
     * @param string $key Help line key.
     * 
     * @return void
    */
    private static function addHelp(array $options, string $key): void 
    {
        if($options === []){
            return;
        }
        
        $minus = ($key === 'usages' || $key === 'examples') ? 1 : 0;
        $color = (($key === 'usages') ? 'yellow' : (($key === 'usages') ? 'lightYellow' : 'lightGreen'));
        static::writeln(ucfirst($key) . ':');

        foreach($options as $info => $values){
            if(is_string($info)){
                static::writeln(TextUtils::padStart('', 8 - $minus) . static::color($info, $color));
                static::writeln(TextUtils::padStart('', 11 - $minus) . $values);
            }else{
                static::writeln(TextUtils::padStart('', 8 - $minus) . $values);
            }
        }
        static::newLine();
    }
}