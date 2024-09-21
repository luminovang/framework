<?php 
/**
 * Luminova Framework CLI Terminal class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Command;

use \Luminova\Application\Foundation;
use \Luminova\Command\Console;
use \Luminova\Command\Utils\Text;
use \Luminova\Command\Utils\Color;
use \Luminova\Security\Validation;
use \Luminova\Command\Novakit\Commands;
use \Luminova\Exceptions\InvalidArgumentException;
use \Closure;

class Terminal 
{
    /**
     * Height of terminal visible window
     *
     * @var int|null $windowHeight
    */
    protected static ?int $windowHeight = null;

    /**
     * Width of terminal visible window
     *
     * @var int|null $windowWidth
    */
    protected static ?int $windowWidth = null;

    /**
     * Is the readline library on the system.
     *
     * @var bool $isReadLine
     */
    public static bool $isReadLine = false;

    /**
     * Write in a new line enabled.
     *
     * @var bool $isNewLine
    */
    protected static bool $isNewLine = false;

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
        defined('STDIN')  || define('STDIN', 'php://stdin');
        defined('STDERR') || define('STDERR', 'php://stderr');
        
        static::$isReadLine = extension_loaded('readline');
        static::$commandsOptions  = [];
        static::$isColored = static::isColorSupported(STDOUT);
    }

    /**
     * Displays a countdown timer in the console, with a custom message pattern, 
     * or prompts the user to press any key to continue.
     *
     * @param int $seconds The number of seconds to wait before continuing (default: 0 seconds).
     * @param string $pattern A custom message pattern to display during the waiting countdown (default: `Waiting...(%d seconds)`). 
     *                        Use '%d' as a placeholder for the remaining seconds.
     *
     * @return void
     * > **Note:** 
     * > During count down, the CLI screen will be wiped out of any output.
     * > If number of seconds is less than 1, it will prompt message `Press any key to continue...`.
     */
    public static final function waiting(
        int $seconds = 0, 
        string $pattern = 'Waiting...(%d seconds)'
    ): void
    {
        if ($seconds <= 0) {
            static::writeln('Press any key to continue...');
            static::input();
            return;
        }

        for ($time = $seconds; $time > 0; $time--) {
            static::fwrite(sprintf("\r{$pattern}", $time));
            sleep(1);
            static::clear();
        }
        static::fwrite(sprintf("\r{$pattern}", 0));
    }

    /**
     * Freeze and pause execution for a specified number of seconds, optionally clear the screen and the user input during the freeze.
     *
     * @param int $seconds The number of seconds to freeze execution (default: 10).
     * @param bool $clear Weather to clear the console screen and input while freezing (default: false).
     *
     * @return void
     */
    public static final function freeze(int $seconds = 10, bool $clear = true): void
    {
        if ($clear) {
            static::clear();
            for ($time = 0; $time < $seconds; $time++) {
                sleep(1);
                static::clear();
                flush();
            }
            return;
        }

        sleep($seconds);
    }

    /**
     * Displays a rotating spinner animation in the CLI.
     *
     * @param array $spinners An array of characters representing each frame of the spinner animation. 
     *                        Default is ['-', '\\', '|', '/'].
     * @param int $spins The number of full rotations (i.e., cycles through the spinner array) to display (default: 10).
     * @param int $sleep The delay in microseconds between each frame of the spinner animation to control animation speed, default is `100,000 (0.1 seconds)`.
     * @param Closure|bool|null $onComplete A callback or boolean indicating what should happen after the spinner finishes (default: null).
     *                                  If true, it outputs "Done!\n". If a Closure is provided, it will be executed.
     *
     * @return void
     */
    public static final function spinner(
        array $spinners = ['-', '\\', '|', '/'], 
        int $spins = 10, 
        int $sleep = 100000, 
        Closure|bool|null $onComplete = null
    ): void
    {
        $count = count($spinners);
        $iterations = $count * $spins;
    
        for ($i = 0; $i < $iterations; $i++) {
            $current = $spinners[$i % $count];
            static::fwrite("\r$current");
            flush();
            usleep($sleep);
        }
    
        static::fwrite("\r"); 
        if ($onComplete === true) {
            static::fwrite("Done!\n");
        } elseif ($onComplete instanceof Closure) {
            $onComplete();
        }
    }

    /**
     * Displays a progress bar in the console for a given number of steps.
     *
     * @param int|false $step The current step in the progress, set to false to indicate completion.
     * @param int $steps The total number of steps for the progress.
     * @param bool $beep Whether to beep when the progress is complete (default: true).
     * 
     * @return float|int Return the percentage of completion between (0-100) or 100 if completed.
     */
    public static final function progress(
        int|bool $step = 1, 
        int $steps = 10, 
        bool $beep = true
    ): float|int
    {
        $percent = 100;
        if ($step === false || $step >= $steps) {
            static::fwrite("\r\033[32m[##########] 100%\033[0m\n");
        }else{
            $step = max(0, $step);
            $steps = max(1, $steps);
            $percent = min(100, max(0, ($step / $steps) * 100));
            $barWidth = (int) round($percent / 10);
            $progressBar = '[' . str_repeat('#', $barWidth) . str_repeat('.', 10 - $barWidth) . ']';
            $progressText = sprintf(' %3d%%', $percent);
            static::fwrite("\r\033[32m" . $progressBar . "\033[0m" . $progressText);
        }

        flush();
        if($beep && $percent >= 100){
            static::beeps(1);
        }
        
        return $percent;
    }

    /**
     * Displays a progress bar on the console and executes optional callbacks at each step and upon completion.
     *
     * This method is designed to be called without a loop, as it handles the iteration internally.
     * It is useful for showing progress while performing a task and executing subsequent actions when complete.
     *
     * @param int $limit The total number of progress steps to display.
     * @param Closure|null $onFinish A callback to execute when the progress reaches 100% (default: null).
     * @param Closure|null $onProgress A callback to execute at each progress step (default: null). 
     * @param bool $beep Indicates whether to emit a beep sound upon completion. Defaults to true.
     *
     * Progress Callback Signature:
     * Receiving the current progress percentage.
     * ```php
     * function(float|int $step): void {
     * }
     * ```
     * @return void
     */
    public static final function watcher(
        int $limit, 
        ?Closure $onFinish = null, 
        ?Closure $onProgress = null, 
        bool $beep = true
    ): void 
    {
        for ($step = 0; $step <= $limit; $step++) {
            $progress = static::progress($step, $limit, $beep);

            if ($onProgress instanceof Closure) {
                $onProgress($progress);
            }

            usleep(100000); 
            if ($progress >= 100) {
                break;
            }
        }

        if ($onFinish instanceof Closure) {
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
     * Optionally, you can make a colored options by using the array key for color name (e.g,`['green' => 'YES', 'red' => 'NO']`).
     *
     *
     * @param string $message The message to prompt.
     * @param array $options  Optional array options to prompt for selection.
     * @param string|false|null $validations Optional validation rules to ensure only the listed options are allowed.
     *                      If null the options values will be used for validation `required|in_array(...values)`.
     *                      To disable validation pass `false` as the value.
     * @param bool $silent Weather to print validation failure message if wrong option was selected (default: false).
     *
     * @return string Return the client input value.
     * 
     * @see https://luminova.ng/docs/0.0.0/security/validation - Read the input validation documentation.
    */
    public static final function prompt(
        string $message, 
        array $options = [], 
        string|bool|null $validations = null, 
        bool $silent = false
    ): string
    {
        $default = '';
        $placeholder = '';
        $textOptions = [];
        $validations = ($validations === false) ? 'none' : $validations;

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
            ? false : ($validationRules !== [] && is_array($validationRules)
            ? "required|in_array(" .  implode(",", $validationRules) . ")" : $validationRules));

        if ($validationRules && str_contains($validationRules, 'required')) {
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
     * Prompt user with multiple option selection.
     * Display array index key as the option identifier to select.
     * If you use associative array users will still see index key instead.
     *
     *
     * @param string $text  The chooser description message to prompt.
     * @param array  $options The list of options to prompt (e.g, ['male' => 'Male', 'female' => 'Female] or ['male', 'female']).
     * @param bool $required Require user to choose any option else the first array will be return as default.
     *
     * @return array<string|int,mixed> Return the client selected array keys and values.
     * @throws InvalidArgumentException Throw if options is not specified or an empty array.
    */
    public static final function chooser(string $text, array $options, bool $required = false): array
    {
        if ($options == []) {
            throw new InvalidArgumentException('Invalid argument, $options is required for chooser.');
        }

        $lastIndex = 0;
        $placeholder = 'To specify multiple values, separate them with commas.';
        $validationRules = '';
        $optionValues = [];
        $index = 0;
        foreach ($options as $key => $value) {
            $optionValues[$index] = [
                'key' => $key,
                'value' => $value
            ];

            $validationRules .= $index . ',';
            $lastIndex = $index;
            $index++;
        }
        
        $validationRules = $required ? "required|keys_exist(" . rtrim($validationRules, ',')  . ")" : 'nullable';
        static::writeln($text);
        self::writeOptions($optionValues, strlen((string) $lastIndex));
        static::writeln($placeholder);

        do {
            if (isset($input)) {
                static::fwrite('Required, please select an option to continue.');
                static::newLine();
            }

            $input = static::input();
            $input = ($input === '') ? '0' : $input;
        } while ($required && !static::validate($input, ['input' => $validationRules]));

        return self::getInputValues(list_to_array($input), $optionValues);
    }

    /**
     * Prompts the user to enter a password, with options for retry attempts and a timeout.
     *
     * @param string $message The message to display when prompting for the password.
     * @param int $retry The number of retry attempts if the user provides an empty password (default: 3).
     * @param int $timeout The number of seconds to wait for user input before displaying an error message (default: 0).
     *
     * @return string Return the client inputted password.
     */
    public static final function password(
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
                    $password = static::_shell("cscript //nologo " . escapeshellarg($vbscript));
                }
    
                if ($password === '' || $password === false || $password === null) {
                    $password = static::_shell('powershell -Command "Read-Host -AsSecureString | ConvertFrom-SecureString"');
                }

                $password = ($password === false || $password === null) ? '' : trim($password);
    
                unlink($vbscript);
            } else {
                $command = "/usr/bin/env bash -c 'echo OK'";
                $continue = static::_shell($command);
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
    
                        if ($result) {
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
                        $password = static::_shell($command);
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
     * Terminates the script execution with an optional message and status code.
     *
     * @param string|null $message  Optional message to display before termination.
     * @param int $exitCode  An exit status code to terminate the script with (default: STATUS_SUCCESS).
     *
     * @return never
     */
    final public static function terminate(?string $message = null, int $exitCode = STATUS_SUCCESS): void
    {
        if ($message !== null) {
            static::writeln($message);
        }

        exit($exitCode);
    }

    /**
     * Highlights url as clickable link in terminal.
     *
     * @param string $url The url to be highlighted.
     * @param string|null $title Optional title to be displayed (default: null).
     *
     * @return never
     */
    final public static function link(string $url, ?string $title = null): void 
    {
        $title ??= $url;
        static::write(static::isAnsiSupported() ? "\033]8;;{$url}\033\\{$title}\033]8;;\033\\" : $url);
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
    public static final function timeout(Closure $callback, int $timeout = 0, mixed $stream = STDIN): bool
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
     * Execute a system command.
     * 
     * @param string $command The command to execute.
     * 
     * @return array|false Return the output of executed command as an array of lines, or false on failure.
     */
    public final function execute(string $command): array|bool
    {
        $result_code = STATUS_ERROR;
        $output = [];

        exec($command, $output, $result_code);
        
        if ($result_code === STATUS_SUCCESS) {
            return $output;
        }

        return false;
    }

    /**
     * Executes a command via `exec` and redirect error output to null based on the platform (Windows or Unix-like).
     * 
     * @param string $command The command to execute.
     * @param array &$output Output lines of the executed command (default: `[]`).
     * @param int &$result_code The exit status of the executed command, passed by reference (default: `STATUS_ERROR`).
     * 
     * @return string|false Return the last line of the command output, or false on failure.
     */
    public static function _exec(
        string $command, 
        array &$output = [], 
        int &$result_code = STATUS_ERROR
    ): string|bool
    {
        $devNull = is_platform('windows') ? '2>NUL' : '2>/dev/null';
        return exec("{$command} {$devNull}", $output, $result_code);
    }

    /**
     * Executes a shell command via `shell_exec` and return the complete output as a string.
     * Also it redirects error output to null based on the platform (Windows or Unix-like).
     * 
     * @param string $command The command to execute.
     * 
     * @return string|false|null Return the output of the command, or null on error.
     */
    public static function _shell(string $command): string|bool|null
    {
        $devNull = is_platform('windows') ? '2>NUL' : '2>/dev/null';
        return shell_exec("{$command} {$devNull}");
    }

    /**
     * Toggles the terminal visibility of user input.
     *
     * @param bool $visibility True to show input, False to hide input.
     * 
     * @return bool Return true if visibility toggling was successful, false otherwise.
     */
    public static final function visibility(bool $visibility = true): bool
    {
        $command = is_platform('windows') 
            ? ($visibility ? 'echo on' : 'echo off')
            : ($visibility ? 'stty echo' : 'stty -echo');

        return static::_shell($command) !== null;
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
            $name = Text::padEnd('  [' . $key . ']  ', $max, ' ');
            static::writeln(static::color($name, 'green') . static::wrap($value['value'], 125, $max));
        }
        static::newLine();
    }

    /**
     * Wrap a text with padding left and width to a maximum number.
     * 
     * @param string|null $text The text to wrap.
     * @param int $max The maximum width to use for wrapping.
     * @param int $leftPadding Additional left padding to apply.
     * 
     * @return string Return wrapped text with padding left and width.
    */
    public static final function wrap(?string $text = null, int $max = 0, int $leftPadding = 0): string
    {
        if (!$text) {
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
        $largest = Text::largest($text)[1];
        $lines = explode(PHP_EOL, $text);
        $text = str_repeat(' ', $largest + 2) . PHP_EOL;

        foreach ($lines as $line) {
            $length = max(0, $largest - Text::strlen($line));
            $text .= ' ' . $line . str_repeat(' ', $length) . ' '  . PHP_EOL;
        }
    
        return $text . str_repeat(' ', $largest + 2);
    }

    /**
     * Attempts to determine the width of the viewable CLI window.
     * 
     * @param int $default Optional default width (default: 80).
     * 
     * @return int Return terminal window width or default.
    */
    public static final function getWidth(int $default = 80): int
    {
        if (static::$windowWidth === null) {
            self::calculateVisibleWindow();
        }

        return static::$windowWidth ?: $default;
    }

    /**
     * Attempts to determine the height of the viewable CLI window.
     * 
     * @param int $default Optional default height (default: 24).
     * 
     * @return int Return terminal window height or default.
    */
    public static final function getHeight(int $default = 24): int
    {
        if (static::$windowHeight === null) {
            self::calculateVisibleWindow();
        }

        return static::$windowHeight ?: $default;
    }

    /**
     * Calculate the visible CLI window width and height.
     *
     * @return void
    */
    private static function calculateVisibleWindow(): void
    {
        if (static::$windowHeight !== null && static::$windowWidth !== null) {
            return;
        }

        if (is_platform('windows')) {
            // Use PowerShell to get console size on Windows
            $size = static::_shell('powershell -command "Get-Host | ForEach-Object { $_.UI.RawUI.WindowSize.Height; $_.UI.RawUI.WindowSize.Width }"');

            if ($size) {
                $dimensions = explode("\n", trim($size));
                static::$windowHeight = (int) $dimensions[0];
                static::$windowWidth = (int) $dimensions[1];
            }
        } else {
            // Fallback for Unix-like systems
            $size = static::_exec('stty size');
            if ($size && preg_match('/(\d+)\s+(\d+)/', $size, $matches)) {
                static::$windowHeight = (int) $matches[1];
                static::$windowWidth  = (int) $matches[2];
            }
        }

        // As a fallback, if still not set, default to standard size
        if (static::$windowHeight === null || static::$windowWidth === null) {
            static::$windowHeight = (int) static::_exec('tput lines');
            static::$windowWidth  = (int) static::_exec('tput cols');
        }
    }

    /**
     * Get user input from the shell, after requesting for user to type or select an option.
     *
     * @param string|null $prompt Optional message to prompt the user after they have typed.
     * @param bool $useFopen Weather to use `fopen`, this opens `STDIN` stream in read-only binary mode (default: false). 
     *                      This creates a new file resource.
     * 
     * @return string Return user input string.
    */
    public static final function input(?string $prompt = null, bool $useFopen = false): string
    {
        if (static::$isReadLine && ENVIRONMENT !== 'testing') {
            return @readline($prompt);
        }

        if ($prompt !== null) {
            echo $prompt;
        }

    
        $input = ($useFopen ? fgets(fopen(STDIN, 'rb')) : fgets(STDIN));
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
    public static final function validate(string $value, array $rules): bool
    {
        static $validation = null;
        $validation ??= new Validation();
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
     * Escape command arguments.
     * 
     * @param string|array $argument The command argument to escape.
     * 
     * @return string Return the escaped command string.
    */
    public static final function escape(?string $argument): string
    {
        if ($argument === '' || $argument === null) {
            return '""';
        }

        if (DIRECTORY_SEPARATOR !== '\\') {
            return "'" . str_replace("'", "'\\''", $argument) . "'";
        }

        if (str_contains($argument, "\0")) {
            $argument = str_replace("\0", '?', $argument);
        }

        if (preg_match('/[\/()%!^"<>&|\s]/', $argument)) {
            $argument = preg_replace('/(\\\\+)$/', '$1$1', $argument);
            return '"' . str_replace(['"', '^', '%', '!', "\n"], ['""', '"^^"', '"^%"', '"^!"', '!LF!'], $argument) . '"';
        }

        return $argument;
    }

    /**
     * Replace command placeholders.
     * 
     * @param string $command The command to replace.
     * @param array $env The environment variables to replace.
     * @param bool $escape Weather escape command after replacements (default: false)
     * 
     * @return string Return replaced command string to be executed.
     * @throws InvalidArgumentException Throws if an error occurs.
     */
    public static final function replace(string $command, array $env, bool $escape = false): string
    {
        return preg_replace_callback('/\$\{:([_a-zA-Z][\w]*)\}/', function ($matches) use ($command, $env, $escape) {
            $key = $matches[1];

            if (!array_key_exists($key, $env) || $env[$key] === false) {
                throw new InvalidArgumentException(sprintf('Missing value for parameter "%s" in command: %s', $key, $command));
            }

            return $escape ? self::escape($env[$key]) : $env[$key];
        }, $command);
    }

    /**
     * Display card error message using red background and white text as default.
     *
     * @param string $text The text to output.
     * @param string|null $foreground Foreground color name.
     * @param string|null $background Optional background color name.
     * 
     * @return void
    */
    public static final function error(
        string $text, 
        string|null $foreground = 'white', 
        ?string $background = 'red'
    ): void
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
     * Display card success message, using green background and white text as default.
     *
     * @param string $text The text to output.
     * @param string|null $foreground Foreground color name.
     * @param string|null $background Optional background color name.
     * 
     * @return void
    */
    public static final function success(
        string $text, 
        string|null $foreground = 'white', 
        ?string $background = 'green'
    ): void
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
     * Print text to in a newline using stream `STDOUT`.
     * 
     * @param string $text The text to write.
     * @param string|null $foreground Optional foreground color name.
     * @param string|null $background Optional background color name.
     *
     * @return void
    */
    public static final function writeln(
        string $text = '', 
        ?string $foreground = null, 
        ?string $background = null
    ): void
    {
        if ($foreground || $background) {
            $text = static::color($text, $foreground, $background);
        }

        if (!static::$isNewLine) {
            $text = PHP_EOL . $text;
            static::$isNewLine = true;
        }

        static::fwrite($text . PHP_EOL);
    }

    /**
     * Print text to without a newline applied using stream `STDOUT`.
     * 
     * @param string $text The text to write.
     * @param string|null $foreground Optional foreground color name.
     * @param string|null $background Optional background color name.
     *
     * @return void
    */
    public static final function write(
        string $text = '', 
        ?string $foreground = null, 
        ?string $background = null
    ): void
    {
     
        if ($foreground || $background) {
            $text = static::color($text, $foreground, $background);
        }

        static::$isNewLine = false;
        static::fwrite($text);
    }

    /**
     * Print a message to using using `echo`.
     *
     * @param string $text The text to print.
     * @param string|null $foreground Optional foreground color name.
     * @param string|null $background Optional background color name.
     *
     * @return void
    */
    public static final function print(
        string $text, 
        ?string $foreground = null, 
        ?string $background = null
    ): void
    {
        if ($foreground || $background) {
            $text = static::color($text, $foreground, $background);
        }

        echo $text;
    }

    /**
     * Write text to stream resource with any handler as needed,
     * If called in non-command context, it will output text using `echo`.
     *
     * @param string $text The text to output or write.
     * @param resource $handle The resource handler to use (e.g. `STDOUT`, `STDIN`, `STDERR` etc...).
     *
     * @return void
    */
    public static final function fwrite(string $text, mixed $handle = STDOUT): void
    {
        if (!is_command()) {
            echo $text;
            return;
        }

        fwrite($handle, $text);
    }

    /**
     * Clears the entire screen for both Windows and Unix-based systems console.
     *
     * @return void
     */
    public static final function clear(): void
    {
        if (is_platform('windows') && !static::streamSupports('sapi_windows_vt100_support', STDOUT)) {
            static::fwrite(static::_shell('cls'));
            static::newLine(40);
            return;
        }

        static::fwrite("\033[H\033[2J");
    }

    /**
     * Clears CLI output line and update new text.
     *
     * @return void
     */
    public static final function flush(): void
    {
        if (is_platform('windows') && !static::streamSupports('sapi_windows_vt100_support', STDOUT)) {
            static::fwrite("\r"); 
            return;
        }

        static::fwrite("\033[1A\033[2K");
    }

    /**
     * Apply color and text formatting to the given text if color is supported.
     *
     * @param string $text The text to apply color to.
     * @param string|null $foreground The foreground color name.
     * @param string|null $background Optional background color name.
     * @param int|null $format Optionally apply text formatting style.
     *
     * @return string Return colored text if color is supported, otherwise return default text.
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

        return Color::apply($text, $format, $foreground, $background);
    }

    /**
     * Print new lines based on specified count.
     *
     * @param int $count The number of new lines to print.
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
     * Oops! command, show an error message for unknown executed command.
     *
     * @param string $command The command that was executed.
     * @param string|null $color Text color for the command (default: red).
     * 
     * @return int Return status code STATUS_ERROR.
    */
    public static function oops(string $command, string|null $color = 'red'): int 
    {
       static::writeln('Unknown command ' . static::color("'$command'", $color) . ' not found', null);

       return STATUS_ERROR;
    }

    /**
     * Generate and print a formatted table header and rows to the console.
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
            $colorLength = Color::length(null, $headerColor, null) + $headerPadding;
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
     * Register command line queries to make it available using `getOptions` etc.
     * The explain command exposes executed command information making it ready to be accessed withing the class context.
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
     * Convert executed command array arguments to their original string form.
     * 
     * @param array $arguments The command arguments from $_SERVER['argv'].
     * 
     * @return string|null Returns the parsed command arguments as a string, or null if the array is empty.
     */
    public static function toString(array $arguments): ?string
    {
        if ($arguments !== []) {
            return implode(' ', $arguments);
        }

        return null;
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
            'exe_string' => self::toString($arguments),
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
            $hasSpace = array_reduce($arguments, fn($carry, $item) => $carry || str_contains($item, ' '), false);

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
                } elseif ($controller && str_contains($arg, '=')) {
                    [$arg, $value] = explode('=', $arg);
                    $result['arguments'][] = $arg;
                    $result['arguments'][] = $value;
                }else{
                    $result['arguments'][] = $arg;
                }
            } else {
                $arg = ltrim($arg, '-');
                $value = null;

                if(str_contains($arg, '=')){
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
        return static::$commandsOptions['arguments'][$index - 1] ?? null;
    }

    /**
     * Get command arguments.
     * 
     * @return array Return command arguments.
    */
    public static final function getArguments(): array
    {
        return static::$commandsOptions['arguments'] ?? [];
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
     * Returns the command controller class method name.
     * 
     * @return string|null Return the command controller class method or null.
    */
    public static final function getMethod(): ?string
    {
        return static::getQuery('classMethod');
    }

    /**
     * Returns the array of options.
     * 
     * @return array Return array of executed command options.
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
     * @return mixed Return command option query value.
    */
    public static final function getQuery(string $name): mixed
    {
        return static::$commandsOptions[$name] ?? null;
    }

    /**
     * Returns the entire command associative that was executed.
     * 
     * @return array Return an associative array of the entire command information
    */
    public static final function getQueries(): array
    {
        return static::$commandsOptions;
    }

    /**
     * Determines if the terminal supports colored output.
     * 
     * @param mixed $resource The resource to check (default is STDOUT).
     * 
     * @return bool Return true if color output is supported, false otherwise.
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
     * Checks if the current terminal supports ANSI escape sequences.
     *
     * @return bool Return true if ANSI is supported, false otherwise.
     */
    public static final function isAnsiSupported(): bool
    {
        static $ansiResult = null;

        if ($ansiResult !== null) {
            return $ansiResult;
        }

        if (is_platform('windows')) {
            return $ansiResult = getenv('ANSICON') === 'ON' || getenv('WT_SESSION') !== false;
        }

        $term = getenv('TERM');
        if ($term !== false) {
            $ansiTerminals = ['xterm', 'xterm-color', 'screen', 'screen-256color', 'tmux', 'linux'];
            foreach ($ansiTerminals as $terminal) {
                if (str_contains($term, $terminal)) {
                    return $ansiResult = true;
                }
            }
        }

        return $ansiResult = false;
    }

    /**
     * Determines if PTY (Pseudo-Terminal) is supported on the current system.
     *
     * @return bool Return true if PTY is supported, false otherwise.
     */
    public static final function isPtySupported(): bool
    {
        static $ptyResult;

        if (null !== $ptyResult) {
            return $ptyResult;
        }

        if ('\\' === DIRECTORY_SEPARATOR) {
            return $ptyResult = false;
        }

        return $ptyResult = (
            self::streamSupports('posix_isatty', STDOUT) || 
            (bool) @proc_open('echo 1 >/dev/null', [['pty'], ['pty'], ['pty']], $pipes)
        );
    }

    /**
     * Checks if the current system supports TTY (Teletypewriter).
     *
     * @return bool Return true if TTY is supported, false otherwise.
     */
    public static final function isTtySupported(): bool
    {
        static $ttyResult;

        return $ttyResult ??= ('/' === DIRECTORY_SEPARATOR && static::streamSupports('stream_isatty', STDOUT));
    }

    /**
     * Checks whether the no color is available in environment.
     *
     * @return bool Return true if color is disabled, false otherwise.
    */
    public static function isColorDisabled(): bool
    {
        return isset($_SERVER['NO_COLOR']) || getenv('NO_COLOR') !== false;
    }

    /**
     * Determines if the current terminal is a supported macOS terminal.
     *
     * @return bool Return true if the terminal is a supported macOS terminal, false otherwise.
     */
    public static final function isMacTerminal(): bool
    {
        static $macResult = null;

        if ($macResult !== null) {
            return $macResult;
        }

        $termProgram = getenv('TERM_PROGRAM');
        if ($termProgram) {
            $macResult = in_array($termProgram, ['Hyper', 'Apple_Terminal']) || (
                $termProgram === 'iTerm' &&
                version_compare(getenv('TERM_PROGRAM_VERSION'), '3.4', '>=')
            );
        } else {
            $macResult = false;
        }

        return $macResult;
    }

    /**
     * Determines if the current terminal is a supported Linux terminal.
     *
     * @return bool Return true if the terminal is a supported Linux terminal, false otherwise.
     */
    public static function isLinuxTerminal(): bool
    {
        static $unixResult = null;

        if ($unixResult !== null) {
            return $unixResult;
        }

        if (stripos(PHP_OS, 'Linux') === 0) {
            return $unixResult = true;
        }

        $termProgram = getenv('TERM_PROGRAM');
        if ($termProgram !== false) {
            return $unixResult = in_array(strtolower($termProgram), ['xterm', 'gnome-terminal', 'konsole', 'terminator']);
        } 
        
        return $unixResult = false;
    }


    /**
     * Checks whether the stream resource on windows is terminal.
     *
     * @param resource|string $resource The resource type to check (e.g. STDIN, STDOUT).
     * 
     * @return bool return true if is windows terminal, false otherwise.
    */
    public static final function isWindowsTerminal(mixed $resource = STDIN): bool
    {
        return static::streamSupports('sapi_windows_vt100_support', $resource) ||
            isset($_SERVER['ANSICON']) || 
            getenv('ANSICON') !== false || 
            getenv('ConEmuANSI') === 'ON' || 
            getenv('TERM') === 'xterm';
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

            return array_key_exists('help', $command) || array_key_exists('h', $command);
        }

        return preg_match('/^(-h|--help|)$/', $command);
    }

    /**
     * Print command help information.
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
            if($all){
                static::newLine();
                static::writeln("------[{$name} Help Information]------");
            }

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
                static::writeln(Text::padStart('', 8 - $minus) . static::color($info, $color));
                static::writeln(Text::padStart('', 11 - $minus) . $values);
            }else{
                static::writeln(Text::padStart('', 8 - $minus) . $values);
            }
        }
        static::newLine();
    }
}