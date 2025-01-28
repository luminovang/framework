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

use \Luminova\Interface\LazyInterface;
use \Luminova\Application\Foundation;
use \Luminova\Command\Console;
use \Luminova\Command\Utils\Text;
use \Luminova\Command\Utils\Color;
use \Luminova\Security\Validation;
use \Luminova\Command\Novakit\Commands;
use \Luminova\Exceptions\InvalidArgumentException;
use \Closure;

class Terminal implements LazyInterface
{
    /**
     * Represents the standard output stream.
     * 
     * @var int STD_OUT
     */
    public const STD_OUT = 0;

    /**
     * Represents the standard error stream.
     * 
     * @var int STD_ERR
     */
    public const STD_ERR = 1;

    /**
     * Represents the standard input stream.
     * 
     * @var int STD_IN
     */
    public const STD_IN = 2;

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
     * The parsed command information from (explain).
     *
     * @var array $explained
     */
    protected static array $explained = [];

    /**
     * Flags to determine if color and ansi are supported 
     * based on different `STDOUT` and `STDERR` resource.
     *
     * @var array{colors:array{0:?bool,1:?bool},ansi:array{0:?bool,1:?bool}} $isSupported
     */
    protected static array $isSupported = [
        'colors' => [
            0 => null, //stdout
            1 => null, //stderr,
            2 => null //stdin
        ],
        'ansi' => [
            0 => null, // stdout
            1 => null, // stderr,
            2 => null //stdin
        ]
    ];

    /**
     * Input validations object.
     * 
     * @var Validation|null $validation;
     */
    protected static ?Validation $validation = null;

    /**
     * Initialize command line instance before running any commands.
     */
    public function __construct()
    {
        defined('STDOUT') || define('STDOUT', 'php://output');
        defined('STDIN')  || define('STDIN', 'php://stdin');
        defined('STDERR') || define('STDERR', 'php://stderr');
        
        self::$isReadLine = extension_loaded('readline');
        self::$explained  = [];
        self::isColorSupported(self::STD_OUT);
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
    public static function waiting(
        int $seconds = 0, 
        string $pattern = 'Waiting...(%d seconds)'
    ): void
    {
        if ($seconds <= 0) {
            self::writeln('Press any key to continue...');
            self::input();
            return;
        }

        for ($time = $seconds; $time > 0; $time--) {
            self::fwrite(sprintf("\r{$pattern}", $time));
            sleep(1);
            self::clear();
        }
        self::fwrite(sprintf("\r{$pattern}", 0));
    }

    /**
     * Freeze and pause execution for a specified number of seconds, 
     * optionally clear the screen and the user input during the freeze.
     *
     * @param int $seconds The number of seconds to freeze execution (default: 10).
     * @param bool $clear Weather to clear the console screen and input while freezing (default: false).
     *
     * @return void
     */
    public static function freeze(int $seconds = 10, bool $clear = true): void
    {
        if ($clear) {
            self::clear();
            for ($time = 0; $time < $seconds; $time++) {
                sleep(1);
                self::clear();
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
    public static function spinner(
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
            self::fwrite("\r$current");
            flush();
            usleep($sleep);
        }
    
        self::fwrite("\r"); 
        if ($onComplete === true) {
            self::fwrite("Done!\n");
            return;
        }
        
        if ($onComplete instanceof Closure) {
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
    public static function progress(
        int|bool $step = 1, 
        int $steps = 10, 
        bool $beep = true
    ): float|int
    {
        $percent = 100;
        if ($step === false || $step >= $steps) {
            self::fwrite("\r" . self::applyAnsi('[##########] 100%', '32m') . "\n");
        }else{
            $step = max(0, $step);
            $steps = max(1, $steps);
            $percent = min(100, max(0, ($step / $steps) * 100));
            $barWidth = (int) round($percent / 10);
            $progressBar = '[' . str_repeat('#', $barWidth) . str_repeat('.', 10 - $barWidth) . ']';
            $progressText = sprintf(' %3d%%', $percent);
            self::fwrite("\r" . self::applyAnsi($progressBar, '32m') . $progressText);
        }

        flush();
        if($beep && $percent >= 100){
            self::beeps(1);
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
     * @return void
     * 
     * @example Progress Callback Signature:
     * Receiving the current progress percentage.
     * ```php
     * Terminal::watcher(5, function(float|int $step): void {
     *    echo "Progress: $step%";
     * });
     * ```
     */
    public static function watcher(
        int $limit, 
        ?Closure $onFinish = null, 
        ?Closure $onProgress = null, 
        bool $beep = true
    ): void 
    {
        for ($step = 0; $step <= $limit; $step++) {
            $progress = self::progress($step, $limit, $beep);

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
    public static function beeps(int $total = 1): void
    {
        self::print(str_repeat("\x07", $total));
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
    public static function prompt(
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
        $isColor = self::isColorSupported();

        if($options !== []){
            foreach($options as $color => $text){
                $textOptions[] = $text;
                $placeholder .= ($isColor ? Color::style($text, $color) : $text) . ',';
            }
            $placeholder = '[' . rtrim($placeholder, ',') . ']';
            $default = $textOptions[0];
        }

        $rules = $validations ?? $textOptions;
        $rules = ($validations === 'none' 
            ? false : ($rules !== [] && is_array($rules)
            ? "required|in_array(" .  implode(",", $rules) . ")" : $rules));

        if ($rules && str_contains($rules, 'required')) {
            $default = '';
        }
        
        do {
            if(!$silent){
                if (isset($input)) {
                    self::fwrite('Input validation failed. ');
                }
                self::fwrite($message . ' ' . $placeholder . ': ');
            }
            $input = self::input();
            $input = ($input === '') ? $default : $input;
        } while ($rules !== false && !self::validate($input, ['input' => $rules]));
    
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
    public static function chooser(string $text, array $options, bool $required = false): array
    {
        if ($options == []) {
            throw new InvalidArgumentException('Invalid argument, $options is required for chooser.');
        }

        $lastIndex = 0;
        $placeholder = 'To specify multiple values, separate them with commas.';
        $rules = '';
        $optionValues = [];
        $index = 0;

        foreach ($options as $key => $value) {
            $optionValues[$index] = [
                'key' => $key,
                'value' => $value
            ];

            $rules .= $index . ',';
            $lastIndex = $index;
            $index++;
        }
        
        $rules = $required ? "required|keys_exist(" . rtrim($rules, ',')  . ")" : 'nullable';
        self::writeln($text);
        self::writeOptions($optionValues, strlen((string) $lastIndex));
        self::writeln($placeholder);

        do {
            if (isset($input)) {
                self::fwrite('Required, please select an option to continue.');
                self::newLine();
            }

            $input = self::input();
            $input = ($input === '') ? '0' : $input;
        } while ($required && !self::validate($input, ['input' => $rules]));

        return self::getInputValues(list_to_array($input), $optionValues);
    }

     /**
     * Prompts the user to enter a password with hidden input. Supports retry attempts and optional timeout.
     *
     * - On Windows, it uses a VBS script or PowerShell to hide the input.
     * - On Linux/Unix, it uses a bash command to hide the input.
     *
     * @param string $message Optional message to display when prompting for the password (default: 'Enter Password').
     * @param int $retry The number of allowed retry attempts, set to `0` for unlimited retries (default: 3).
     * @param int $timeout Optional time window for password input in seconds, set to 0 for no timeout (default: 0).
     * 
     * @return string Return the entered password or an empty string if the maximum retry attempts are exceeded.
     */
    public static function password(
        string $message = 'Enter Password', 
        int $retry = 3, 
        int $timeout = 0
    ): string
    {
        $attempts = 0;
        $isVisibilityPromptShown = false;
        $isWindows = (is_platform('windows') || self::isWindowsTerminal(self::STD_IN));

        do {
            $password = $isWindows
                ? self::getWindowsHiddenPassword($message, $timeout)
                : self::getLinuxHiddenPassword($message, $timeout, $isVisibilityPromptShown);
    
            if ($password !== '') {
                self::newLine();
                return $password;
            }
    
            $attempts++;
            $isVisibilityPromptShown = true;
            if ($retry === 0 || $attempts < $retry) {
                self::newLine();
                self::error("Error: Password cannot be empty. Please try again.");
            }
        } while ($retry === 0 || $attempts < $retry);
    
        if($retry !== 0){
            self::newLine();
            self::error("Error: Maximum retry attempts reached. Exiting.");
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
    public static function terminate(?string $message = null, int $exitCode = STATUS_SUCCESS): void
    {
        if ($message !== null) {
            self::writeln($message);
        }

        exit($exitCode);
    }

    /**
     * Highlights URL to indicate it clickable in terminal.
     *
     * @param string $url The url to be highlighted.
     * @param string|null $title Optional title to be displayed (default: null).
     *
     * @return void
     */
    public static function link(string $url, ?string $title = null): void 
    {
        $title ??= $url;
        self::write(self::isAnsiSupported() 
            ? "\033]8;;{$url}\033\\{$title}\033]8;;\033\\" 
            : $url
        );
    }

    /**
     * Execute a callback function after a specified timeout when no input or output is received.
     *
     * @param Closure $callback The callback function to execute on timeout.
     * @param int $timeout Timeout duration in seconds. If <= 0, callback is invoked immediately (default: 0).
     * @param resource|string|int $stream Optional stream to monitor for activity (default: STDIN).
     * 
     * @return bool Returns true if the timeout occurred and callback was executed, otherwise false.
     */
    public static function timeout(Closure $callback, int $timeout = 0, mixed $stream = self::STD_IN): bool
    {
        if ($timeout <= 0) {
            $callback();
            return true;
        }
    
        $read = [self::getStd($stream)];
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
    public static function execute(string $command): array|bool
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
    public static function visibility(bool $visibility = true): bool
    {
        $command = is_platform('windows') 
            ? ($visibility ? 'echo on' : 'echo off')
            : ($visibility ? 'stty echo' : 'stty -echo');

        return self::_shell($command) !== null;
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
        $isColor = self::isColorSupported();

        foreach ($options as $key => $value) {
            $name = Text::padding('  [' . $key . ']  ', $max, Text::LEFT);
            $name = ($isColor ? Color::style($name, 'green') : $name);

            self::writeln($name . Text::wrap($value['value'], 125, $max));
        }

        self::newLine();
    }

    /**
     * Attempts to determine the width of the viewable CLI window.
     * 
     * @param int $default Optional default width (default: 80).
     * 
     * @return int Return terminal window width or default.
     */
    public static function getWidth(int $default = 80): int
    {
        if (self::$windowWidth === null) {
            self::getVisibleWindow();
        }

        return self::$windowWidth ?: $default;
    }

    /**
     * Attempts to determine the height of the viewable CLI window.
     * 
     * @param int $default Optional default height (default: 24).
     * 
     * @return int Return terminal window height or default.
     */
    public static function getHeight(int $default = 24): int
    {
        if (self::$windowHeight === null) {
            self::getVisibleWindow();
        }

        return self::$windowHeight ?: $default;
    }

    /**
     * Get user input from the shell, after requesting for user to type or select an option.
     *
     * @param string|null $prompt Optional message to prompt the user after they have typed.
     * @param bool $use_fopen Weather to use `fopen`, this opens `STDIN` stream in read-only binary mode (default: false). 
     *                      This creates a new file resource.
     * 
     * @return string Return user input string.
     */
    public static function input(?string $prompt = null, bool $use_fopen = false): string
    {
        if (self::$isReadLine && ENVIRONMENT !== 'testing') {
            return @readline($prompt);
        }

        if ($prompt !== null) {
            self::print($prompt);
        }

        if ($use_fopen) {
            $handle = fopen(STDIN, 'rb');
            $input = fgets($handle);
            fclose($handle);
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
    public static function validate(string $value, array $rules): bool
    {
        self::$validation ??= new Validation();
        self::$validation->setRules($rules);
        $field = [
            'input' => $value
        ]; 

        if (!self::$validation->validate($field)) {
            self::error(self::$validation->getError('input'));
            return false;
        }

        return true;
    }

    /**
     * Escapes a command argument to ensure safe execution in the shell.
     * 
     * @param string $argument The command argument to escape.
     * 
     * @return string Return the escaped command string.
     */
    public static function escape(?string $argument): string
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
     * Replace placeholders in a command with environment variable values.
     * 
     * Placeholders follow the format `${:VAR_NAME}`, where `VAR_NAME` corresponds to a key in the `$env` array.
     * Optionally escapes each replacement for safe shell execution.
     * 
     * @param string $command The command string with placeholders to replace.
     * @param array<string,mixed> $env Associative array of environment variables for replacements.
     * @param bool $escape Whether to escape each replacement after substitution (default: false).
     * 
     * @return string Return the command string with placeholders replaced.
     * @throws InvalidArgumentException Throws if a placeholder variable is not found in `$env` or is set to `false`.
     */
    public static function replace(string $command, array $env, bool $escape = false): string
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
     * Display an error message box with a default red background and white text.
     * The message is formatted as a block and written to `STDERR`.
     * 
     * @param string $text The error message to display.
     * @param string|null $foreground The text color (default: white).
     * @param string|null $background The background color (default: red).
     *
     * @return void
     */
    public static function error(
        string $text, 
        string|null $foreground = 'white', 
        string|null $background = 'red'
    ): void
    {
        if(($foreground || $background) && !self::isColorSupported(self::STD_ERR)){
            $foreground = $background = null;
        }

        self::fwrite(Text::block($text, Text::LEFT, 1, $foreground, $background), self::STD_ERR);
    }

    /**
     * Display a success message with a default green background and white text.
     * The message is formatted as a block and written to `STDOUT`.
     * 
     * @param string $text The success message to display.
     * @param string|null $foreground The text color (default: white).
     * @param string|null $background The background color (default: green).
     *
     * @return void
     */
    public static function success(
        string $text, 
        string|null $foreground = 'white', 
        string|null $background = 'green'
    ): void
    {
        if(($foreground || $background) && !self::isColorSupported(self::STD_OUT)){
            $foreground = $background = null;
        }

        self::fwrite(Text::block($text, Text::LEFT, 1, $foreground, $background));
    }

    /**
     * Print text followed by a newline to the specified stream, defaulting to `Terminal::STD_OUT`.
     * Optionally apply foreground and background colors to the text.
     * 
     * @param string $text The text to write (default: blank).
     * @param string|null $foreground Optional foreground color name.
     * @param string|null $background Optional background color name.
     * @param int $stream The stream resource to write to (e.g, `Terminal::STD_OUT`, `Terminal::STD_IN`, `Terminal::STD_ERR`).
     *
     * @return void
     */
    public static function writeln(
        string $text = '', 
        ?string $foreground = null, 
        ?string $background = null,
        int $stream = self::STD_OUT
    ): void
    {
        if(($foreground || $background) && self::isColorSupported($stream)){
            $text = Color::style($text, $foreground, $background);
        }

        if (!self::$isNewLine) {
            $text = PHP_EOL . $text;
            self::$isNewLine = true;
        }

        self::fwrite($text . PHP_EOL, $stream);
    }

    /**
     * Print text without appending a newline to the specified stream, defaulting to `Terminal::STD_OUT`.
     * Optionally apply foreground and background colors to the text.
     * 
     * @param string $text The text to write (default: blank).
     * @param string|null $foreground Optional foreground color name.
     * @param string|null $background Optional background color name.
     * @param int $stream The stream resource to write to (e.g, `Terminal::STD_OUT`, `Terminal::STD_IN`, `Terminal::STD_ERR`).
     *
     * @return void
     */
    public static function write(
        string $text = '', 
        ?string $foreground = null, 
        ?string $background = null,
        int $stream = self::STD_OUT
    ): void
    {
        self::$isNewLine = false;
        if(($foreground || $background) && self::isColorSupported($stream)){
            $text = Color::style($text, $foreground, $background);
        }

        self::fwrite($text, $stream);
    }

    /**
     * Print text directly using `echo` without any stream handling.
     * Optionally apply foreground and background colors to the text.
     * 
     * @param string $text The text to print.
     * @param string|null $foreground Optional foreground color name.
     * @param string|null $background Optional background color name.
     *
     * @return void
     */
    public static function print(
        string $text, 
        ?string $foreground = null, 
        ?string $background = null
    ): void
    {
        if (($foreground || $background) && self::isColorSupported()) {
            $text = Color::style($text, $foreground, $background);
        }

        echo $text;
    }

    /**
     * Write text to the specified stream resource without applying any colors, defaulting to `STDOUT`.
     * If the environment is non-command-based, the text will be output using `echo` instead of `fwrite`.
     * 
     * @param string $text The text to output or write.
     * @param resource|int $handler The stream resource handler to write to (e.g. `STDOUT`, `STDERR`, `STDIN`).
     *
     * @return void
     */
    public static function fwrite(string $text, mixed $handler = self::STD_OUT): void
    {
        if (!is_command()) {
            echo $text;
            return;
        }

        $handler = self::getStd($handler);
        fwrite($handler, $text);

        if (!in_array($handler, [STDOUT, STDERR, STDIN], true) && !stream_isatty($handler)) {
            @fclose($handler);
        }
    }

    /**
     * Clears the entire console screen for both Windows and Unix-based systems.
     *
     * @return void
     */
    public static function clear(): void
    {
        if (is_platform('windows') && !self::isStreamSupports('sapi_windows_vt100_support', self::STD_OUT)) {
            self::fwrite(self::_shell('cls'));
            self::newLine(40);
            return;
        }
   
        self::fwrite("\033[H\033[2J");
    }

    /**
     * Clears the last printed line or lines of text in the terminal output.
     *
     * @param string|null $lastOutput An optional string representing the last output printed
     *                                 to the terminal (default: null). If provided, the method 
     *                                 clears the specific lines that match this output.
     * 
     * @return void
     */
    public static function flush(?string $lastOutput = null): void
    {
        if (is_platform('windows') && !self::isStreamSupports('sapi_windows_vt100_support', self::STD_OUT)) {
            self::fwrite("\r"); 
            return;
        }

        if(!$lastOutput){
            self::fwrite("\033[1A\033[2K");
            return;
        }
   
        $lines = explode(PHP_EOL, wordwrap($lastOutput, self::getWidth(), PHP_EOL, true));
        $numLines = count($lines);

        for ($i = 0; $i < $numLines; $i++) {
            self::fwrite("\033[1A\033[2K");
        }
    }

    /**
     * Apply color and text formatting to the given text if color is supported.
     *
     * @param string $text The text to apply color to.
     * @param string $foreground The foreground color name.
     * @param string|null $background Optional background color name.
     * @param int|null $format Optionally apply text formatting style.
     *
     * @return string Return colored text if color is supported, otherwise return default text.
     * @deprecated This method is deprecated and will be removed in a future release. 
     *              Use `Luminova\Command\Utils\Color::apply(...)` or `Luminova\Command\Utils\Color::style(...)` instead.
     */
    public static function color(
        string $text, 
        string $foreground, 
        ?string $background = null, 
        ?int $format = null
    ): string
    {
        if (!self::isColorSupported()) {
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
    public static function newLine(int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            self::writeln();
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
        $command = "'{$command}'";
        $command = self::isColorSupported() 
            ? Color::style($command, $color) 
            : $command;

       self::fwrite("Unknown command {$command} not found." . PHP_EOL, self::STD_ERR);
       return STATUS_ERROR;
    }

    /**
     * Generate and print a formatted table with headers and rows to the console.
     *
     * @param array<int,string> $headers The headers for the table columns, 
     *                                    where each header is defined by its index.
     * @param array<int,array<string,string>> $rows The rows of table data to display in the tables body, 
     *                                                where each row is an associative array with keys 
     *                                                representing the column headers and values containing 
     *                                                the corresponding content.
     * @param string|null $foreground An optional text color for the table's body content (default: null).
     * @param string|null $headerColor An optional text color for the table header columns (default: null).
     * @param string|null $borderColor An optional color for the table borders (default: null).
     * @param bool $border Indicate whether to display borders between each table raw (default: true).
     * 
     * @return string Return the formatted table as a string, ready to be output to the console.
     * 
     * @example Table example:
     * 
     * ```php
     * Terminal::table(
     *     ['Name', 'Email'], 
     *     [
     *         ['Name' => 'Peter', 'Email' => 'peter@example.com'],
     *         ['Name' => 'Hana', 'Email' => 'hana@example.com']
     *     ]
     * );
     * ```
     */
    public static function table(
        array $headers, 
        array $rows, 
        ?string $foreground = null,
        ?string $headerColor = null, 
        ?string $borderColor = null,
        bool $border = true
    ): string
    {
        $chars = [
            'topLeft' => Text::corners('topLeft'),
            'topRight' => Text::corners('topRight'),
            'bottomLeft' => Text::corners('bottomLeft'),
            'bottomRight' => Text::corners('bottomRight'),
            'topConnector' => Text::corners('topConnector'),
            'bottomConnector' => Text::corners('bottomConnector'),
            'crossings' => Text::corners('crossings'),
            'leftConnector' => Text::corners('leftConnector'),
            'rightConnector' => Text::corners('rightConnector'),
            'horizontal' => Text::corners('horizontal'),
            'vertical' => Text::corners('vertical')
        ];
    
        $widths = array_map(
            fn($header) => max(
                Text::largest($header)[1], 
                max(array_map(fn($value) => Text::largest($value)[1], array_column($rows, $header)))
            ), 
            $headers
        );
    
        $heights = array_map(
            fn($row) => max(array_map(fn($header) => Text::height($row[$header]), $headers)),
            $rows
        );
    
        $table = '';
        $table .= self::tBorder($widths, $chars, 'topLeft', 'topConnector', 'topRight', $borderColor);
        $table .= self::trow($headers, $widths, $headerColor ?? $foreground, $borderColor, $chars, true);
        $table .= self::tBorder($widths, $chars, 'leftConnector', 'crossings', 'rightConnector', $borderColor);
    
        foreach ($rows as $index => $row) {
            $height = $heights[$index];
            $lines = array_map(
                fn($header) => Text::lines(Text::wrap($row[$header], $widths[array_search($header, $headers)])),
                $headers
            );
    
            for ($lIdx = 0; $lIdx < $height; $lIdx++) {
                $tData = Color::style($chars['vertical'], $borderColor);
                foreach ($headers as $i => $header) {
                    $tData .= ' ' . Color::style(str_pad($lines[$i][$lIdx] ?? '', $widths[$i]), $foreground);
                    $tData .= ' ' . Color::style($chars['vertical'], $borderColor);
                }
                $table .= $tData . PHP_EOL;
            }
    
            if ($border && $index < count($rows) - 1) {
                $table .= self::tBorder(
                    $widths, 
                    $chars, 
                    'leftConnector', 
                    'crossings', 
                    'rightConnector', 
                    $borderColor
                );
            }
        }
    
        $table .= self::tBorder(
            $widths, 
            $chars, 
            'bottomLeft', 
            'bottomConnector', 
            'bottomRight', 
            $borderColor
        );
    
        return $table;
    }

    /**
     * Extract and expose command arguments and options.
     * 
     * This method processes the executed command within the controller class, making it accessible using methods like 
     * `getOption` and `getAnyOption`. It acts as a setter to store and expose command information for use within extended child classes.
     * 
     * @param array<string,mixed> $options Command arguments, options, and flags extracted from the executed command.
     * 
     * @return void
     * @internal
     * 
     * @example Usage Example:
     * ```php
     * class Command extends Luminova\Base\BaseConsole 
     * {
     *      public function run(?array $options = []): int
     *      {
     *          $this->term->explain($options);
     *          
     *          // Access the command and options
     *          $command = $this->term->getCommand();
     *          $foo = $this->term->getOption('foo');
     *          $fooAlias = $this->term->getAnyOption('foo', 'f');
     *      }
     * }
     * ```
     */
    public static final function explain(array $options): void
    {
        self::$explained = $options;
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
        return ($arguments === []) 
            ? null 
            : implode(' ', $arguments);
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
                $result['group'] = $arguments[0] ?? '';
                $result['command'] = $arguments[1] ?? '';
            }else{
                $result['command'] = $arguments[0] ?? '';
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

        $response = self::extract($arguments);
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
                    [$arg, $value] = explode('=', $arg, 2);
                    $result['arguments'][] = $arg;
                    $result['arguments'][] = $value;
                }else{
                    $result['arguments'][] = $arg;
                }
            } else {
                $arg = ltrim($arg, '-');
                $value = null;

                if(str_contains($arg, '=')){
                    [$arg, $value] = explode('=', $arg, 2);
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
    public static function getArgument(int $index): mixed
    {
        return self::$explained['arguments'][$index - 1] ?? null;
    }

    /**
     * Get command arguments.
     * 
     * @return array Return command arguments.
     */
    public static function getArguments(): array
    {
        return self::$explained['arguments'] ?? [];
    }

    /**
     * Get command name.
     * 
     * @return string|null Return the command name.
     */
    public static function getCommand(): ?string
    {
        return self::$explained['command'] ?? null;
    }

    /**
     * Get command caller command string.
     * 
     * @return string|null Return the full passed command, options and arguments.
     */
    public static function getCaller(): ?string
    {
        return self::$explained['caller'] ?? null;
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
    public static function getOption(string $key, mixed $default = false): mixed
    {
        $options = self::getOptions();

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
    public static function getAnyOption(string $key, string $alias, mixed $default = false): mixed
    {
        $options = self::getOptions();

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
    public static function getMethod(): ?string
    {
        return self::getQuery('classMethod');
    }

    /**
     * Returns the array of options.
     * 
     * @return array Return array of executed command options.
     */
    public static function getOptions(): array
    {
        return self::$explained['options']??[];
    }

    /**
     * Gets a single query command-line by name, if it doesn't exists return null.
     *
     * @param string $name Option key name.
     * 
     * @return mixed Return command option query value.
    */
    public static function getQuery(string $name): mixed
    {
        return self::$explained[$name] ?? null;
    }

    /**
     * Returns the entire command associative that was executed.
     * 
     * @return array Return an associative array of the entire command information
     */
    public static function getQueries(): array
    {
        return self::$explained;
    }

    /**
     * Force enables color output for all standard streams (STDOUT, STDERR, STDIN)
     * without checking whether the terminal or console supports colors. 
     * 
     * @return void
     */
    public static function setColorOutputEnabled(): void 
    {
        self::forceEnableSupport('colors');
    }

    /**
     * Force enables ANSI escape codes (for formatting like text color, cursor movement, etc.) 
     * for all standard streams (STDOUT, STDERR, STDIN), without checking whether 
     * the terminal or console supports ANSI codes. 
     * 
     * @return void
     */
    public static function setAnsiSupportEnabled(): void 
    {
        self::forceEnableSupport('ansi');
    }

    /**
     * Resolves the provided stream constant (`Terminal::STD_OUT`, `Terminal::STD_ERR`, or `Terminal::STD_IN`) and 
     * returns the corresponding PHP predefined stream (`STDOUT`, `STDERR`, or `STDIN`).
     * If the input does not match any of these constants, it returns the original input.
     *
     * @param resource|int $std The stream identifier, which can be one of the predefined 
     *                   constants `Terminal::STD_*` or another value.
     * 
     * @return resource|string Return the corresponding PHP stream resource (`STDOUT`, `STDERR`, `STDIN`) 
     *               or the original custom stream handler if it doesn't match any standard-global streams.
     */
    public static final function getStd(mixed $std): mixed
    {
        return ($std === self::STD_OUT) ? STDOUT 
            : (($std === self::STD_ERR) ? STDERR
            : (($std === self::STD_IN) ? STDIN : $std));
    }

    /**
     * Checks if the terminal supports ANSI escape codes for color output.
     *
     * @param int $std The std resource to check for color support (e.g, `Terminal::STD_OUT`, `Terminal::STD_ERR` or `Terminal::STD_IN`).
     * 
     * @return bool Returns true if color output is supported, false otherwise.
     */
    public static function isColorSupported(int $std = self::STD_OUT): bool
    {
        if((self::$isSupported['colors'][$std] ?? null) !== null){
            return self::$isSupported['colors'][$std];
        }

        self::$isSupported['colors'][$std] = false;

        if (!self::isColorDisabled()) {
            if (is_platform('mac')) {
                self::$isSupported['colors'][$std] = self::isMacTerminal();
            }elseif (is_platform('windows')) {
                self::$isSupported['colors'][$std] = self::isWindowsTerminal($std);
            }else{
                self::$isSupported['colors'][$std] = self::isStreamSupports('stream_isatty', $std);
            }
        }

        return self::$isSupported['colors'][$std];
    }

    /**
     * Checks if the terminal supports ANSI escape codes, including color and text formatting.
     *
     * @param int $std The std resource to check for ANSI support (e.g, `Terminal::STD_OUT`, `Terminal::STD_ERR` or `Terminal::STD_IN`).
     * 
     * @return bool Returns true if ANSI escape codes are supported, false otherwise.
     */
    public static function isAnsiSupported(int $std = self::STD_OUT): bool
    {
        if ((self::$isSupported['ansi'][$std] ?? null) !== null) {
            return self::$isSupported['ansi'][$std];
        }

        self::$isSupported['ansi'][$std] = false;

        if (!self::isAnsiDisabled()) {
            self::$isSupported['ansi'][$std] = is_platform('windows') 
                ? getenv('ANSICON') === 'ON' || getenv('WT_SESSION') !== false
                :  self::isLinuxAnsi();
        }

        return self::$isSupported['ansi'][$std];
    }

    /**
     * Determines if PTY (Pseudo-Terminal) is supported on the current system.
     *
     * @return bool Return true if PTY is supported, false otherwise.
     */
    public static function isPtySupported(): bool
    {
        static $ptyResult;

        if (null !== $ptyResult) {
            return $ptyResult;
        }

        if ('\\' === DIRECTORY_SEPARATOR) {
            return $ptyResult = false;
        }

        return $ptyResult = (
            self::isStreamSupports('posix_isatty', self::STD_OUT) || 
            (bool) @proc_open('echo 1 >/dev/null', [['pty'], ['pty'], ['pty']], $pipes)
        );
    }

    /**
     * Checks if the current system supports TTY (Teletypewriter).
     *
     * @return bool Return true if TTY is supported, false otherwise.
     */
    public static function isTtySupported(): bool
    {
        static $ttyResult;
        return $ttyResult ??= ('/' === DIRECTORY_SEPARATOR && self::isStreamSupports('stream_isatty', self::STD_OUT));
    }

    /**
     * Checks whether the no color is available in environment.
     *
     * @return bool Return true if color is disabled, false otherwise.
     */
    public static function isColorDisabled(): bool
    {
        return (
            self::hasFlag('--no-color') || 
            isset($_SERVER['NO_COLOR']) || 
            getenv('NO_COLOR') !== false
        );
    }

    /**
     * Determines if ANSI escape codes are disabled explicitly.
     * 
     * @return bool Returns `true` if ANSI escape codes are disabled, `false` otherwise.
     */
    public static function isAnsiDisabled(): bool
    {
        return (
            self::hasFlag('--no-ansi') || 
            isset($_SERVER['DISABLE_ANSI']) || 
            getenv('DISABLE_ANSI') !== false
        );
    }
    
    /**
     * Determines if a specific command-line flag is present.
     * 
     * @param string $flag The flag to search for (e.g., `--no-ansi`, `--no-color`, `--no-header`).
     * 
     * @return bool Returns true if the flag is present, false otherwise.
     */
    public static function hasFlag(string $flag): bool
    {
        $options = self::getOptions();
        return ($options !== [] && array_key_exists($flag, $options)) 
            ? true 
            : in_array($flag, $_SERVER['argv']);
    }

    /**
     * Determines if the current terminal is a supported macOS terminal.
     *
     * @return bool Return true if the terminal is a supported macOS terminal, false otherwise.
     */
    public static function isMacTerminal(): bool
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
     * @param resource|string|int $resource The resource type to check (e.g. `Terminal::STD_*`, `STDIN`, `STDOUT`).
     * 
     * @return bool Return true if is windows terminal, false otherwise.
     */
    public static function isWindowsTerminal(mixed $resource = self::STD_IN): bool
    {
        return self::isStreamSupports('sapi_windows_vt100_support', $resource) ||
            isset($_SERVER['ANSICON']) || 
            getenv('ANSICON') !== false || 
            getenv('ConEmuANSI') === 'ON' || 
            getenv('TERM') === 'xterm';
    }

    /**
     * Checks whether the current stream resource supports or refers to a valid terminal type device.
     *
     * @param string $function Function name to check.
     * @param resource|string|int $resource Resource to handle (e.g. `Terminal::STD_*`, `STDIN`, `STDOUT`).
     * 
     * @return bool Return true if stream resource is supported, otherwise false.
     */
    public static function isStreamSupports(string $function, mixed $resource = self::STD_OUT): bool
    {
        if (!function_exists($function)) {
            return false;
        }
        
        return (ENVIRONMENT === 'testing') 
            ? true 
            : @$function(self::getStd($resource));
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
            return Console::execute($terminal, $options) === STATUS_SUCCESS;
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
        if(!$command){
            return false;
        }

        if(is_array($command)){
            $command = ($command['options'] ?? $command);
            return array_key_exists('help', $command) || array_key_exists('h', $command);
        }

        return preg_match('/^(-h|--help)$/', $command) === 1;
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
        $helps = ($helps === null) 
            ? Commands::getCommands() 
            : ($all ? $helps : [$helps]);

        foreach($helps as $name => $help){
            if($all){
                self::newLine();
                self::writeln("------[{$name} Help Information]------");
            }

            foreach($help as $key => $value){
                if($key === 'description'){
                    self::writeln('Description:');
                    self::writeln($value);
                    self::newLine();
                }

                if($key === 'usages'){
                    if(is_array($value)){
                        self::printHelp($value, $key);
                    }else{
                        self::writeln('Usages:');
                        self::writeln($value);
                        self::newLine();
                    }
                }

                if(is_array($value)){
                    if($key === 'options'){
                        self::printHelp($value, $key);
                    }

                    if($key === 'examples'){
                        self::printHelp($value, $key);
                    }
                }
            }
        }
    }

    /**
     * Print NovaKit Command line header information.
     * 
     * @return bool Return true if header was printed, false otherwise.
     */
    public static final function header(): bool
    {
        if(self::hasFlag('--no-header')){
            return false;
        }

        self::write(sprintf(
            'PHP Luminova v%s NovaKit Command Line Tool v%s - Server Time: %s UTC%s',
            Foundation::VERSION,
            Foundation::NOVAKIT_VERSION,
            date('Y-m-d H:i:s'),
            date('P')
        ), 'green');
        return true;
    }

    /**
     * Add help information.
     * 
     * @param array $option Help line options.
     * @param string $key Help line key.
     * 
     * @return void
     */
    private static function printHelp(array $options, string $key): void 
    {
        if($options === []){
            return;
        }
        
        $minus = ($key === 'usages' || $key === 'examples') ? 1 : 0;
        $color = self::isColorSupported() 
            ? (($key === 'usages') ? 'yellow' : (($key === 'usages') ? 'lightYellow' : 'lightGreen'))
            : null;
        self::writeln(ucfirst($key) . ':');
        
        foreach($options as $info => $values){
            if(is_string($info)){ 
                $info  = ($color === null) ? $info : Color::style($info, $color);

                self::writeln(Text::padding('', 8 - $minus, Text::RIGHT) . $info);
                self::writeln(Text::padding('', 11 - $minus, Text::RIGHT) . $values);
            }else{
                self::writeln(Text::padding('', 8 - $minus, Text::RIGHT) . $values);
            }
        }
        self::newLine();
    }

    /**
     * Safely apply ANSI formatting acknowledging `--no-ansi` flag.
     * 
     * @param string $text The text to be formatted.
     * @param string $format The format to be used.
     * 
     * @return string Return the formatted text.
     */
    private static function applyAnsi(string $text, string $format): string 
    {
        if(!self::isAnsiSupported()){
            return $text;
        }

        return "\033[{$format}{$text}\033[0m";
    }

    /**
     * Generates a horizontal border line for the table based on column widths.
     *
     * @param array $widths Array of column widths.
     * @param array $chars Array of table border characters.
     * @param string $left Character used for the left corner of the border line.
     * @param string $connector Character used to connect columns within the border line.
     * @param string $right Character used for the right corner of the border line.
     * @param ?string $color Optional color to apply to the border line.
     * 
     * @return string Return formatted string representing the horizontal border line.
     */
    private static function tBorder(
        array $widths, 
        array $chars, 
        string $left, 
        string $connector, 
        string $right, 
        ?string $color
    ): string
    {
        $border = $chars[$left];
        foreach ($widths as $i => $width) {
            $border .= str_repeat($chars['horizontal'], $width + 2);
            $border .= $i < count($widths) - 1 
                ? $chars[$connector]
                : $chars[$right];
        }

        return Color::style($border, $color) . PHP_EOL;
    }
    
    /**
     * Generates a table row with formatted cell values or headers.
     *
     * @param array $row Array of cell values for each column in the row.
     * @param array $widths Array of column widths, matching the indices of $row.
     * @param ?string $foreground Optional color to apply to the cell content.
     * @param array $chars Array of table border characters.
     * @param bool $isHeader Flag indicating if the row is a header row; applies bold styling if true.
     * 
     * @return string Return formatted string representing the row with each cell padded to its column width.
     */
    private static function trow(
        array $row, 
        array $widths, 
        ?string $foreground, 
        ?string $borderColor, 
        array $chars, 
        bool $isHeader = false
    ): string
    {
        $tCell = Color::style($chars['vertical'], $borderColor);
        foreach ($row as $i => $cell) {
            $tCell .= ' ' . Color::apply(
                str_pad($cell, $widths[$i]), 
                $isHeader ? Text::FONT_BOLD : Text::NO_FONT, 
                $foreground
            );
            $tCell .= ' ' . Color::style($chars['vertical'], $borderColor);
        }

        return $tCell . PHP_EOL;
    }

    /**
     * Checks if command is executing from the list of supports ANSI terminals.
     *
     * @return bool Return true if terminal is in list of ANSI supported, false otherwise.
     */
    protected static function isLinuxAnsi(): bool
    {
        $term = getenv('TERM');
        if ($term !== false) {
            $supported = ['xterm', 'xterm-color', 'screen', 'screen-256color', 'tmux', 'linux'];
            foreach ($supported as $terminal) {
                if (str_contains($term, $terminal)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Force enables the specified feature (e.g., 'colors', 'ansi') for all standard streams 
     * (STDOUT, STDERR, STDIN) without checking for terminal support.
     * 
     * @param string $type The feature type to enable (e.g., 'colors', 'ansi').
     * 
     * @return void
     */
    private static function forceEnableSupport(string $type): void 
    {
        self::$isSupported[$type][self::STD_OUT] = true;
        self::$isSupported[$type][self::STD_ERR] = true;
        self::$isSupported[$type][self::STD_IN] = true;
    }

    /**
     * Prompts the user to input a hidden password in a Unix-based terminal.
     *
     * @param string $message The message to display to the user prompting for the password.
     * @param int $timeout The maximum time in seconds to wait for user input before timing out.
     * @param bool $visibility Indicates whether password visibility is visible and prompt user if they wish to continue.
     *
     * @return string Return the entered password, or an empty string if no password was provided or an error occurred.
    */
    protected static function getLinuxHiddenPassword(string $message, int $timeout, bool $invisible): string
    {
        $password = '';
        $command = "/usr/bin/env bash -c 'echo OK'";
        $continue = self::_shell($command);
        $continue = ($continue === false || $continue === null) ? 'ERR' : trim($continue);

        if ($continue !== 'OK') {
            $continue = 'yes';

            if (!$invisible && self::visibility(false) === false) {
                $continue = self::prompt('Your password may be visible while typing, do you wish to continue?', [
                    'yes', 'no'
                ], 'required|in_array(yes,no)');
            }
        }

        if ($continue !== 'no') {
            self::fwrite($message . ': ');

            if ($continue === 'yes') {
                self::visibility(false);
            }

            if ($timeout > 0) {
                $result = self::timeout(static function() {
                    self::newLine();
                    self::error("Error: Timeout exceeded. No input provided.");
                }, $timeout);

                if ($result) {
                    if ($continue === 'yes') {
                        self::visibility(true);
                    }
                    
                    return '';
                }
            }

            if ($continue === 'yes') {
                $password = self::input();
                self::visibility(true);

                return $password;
            }
            
            if ($continue === 'OK') {
                $command = "/usr/bin/env bash -c 'read -s inputPassword && echo \$inputPassword'";
                $password = self::_shell($command);
                return !$password ? '' : trim($password);
            }
        }

        return $password;
    }

    /**
     * Prompts the user to input a hidden password using a Windows dialog box, 
     * which utilizes a temporary VBScript file to create an input box for password entry.
     *
     * @param string $message The message to display to the user prompting for the password.
     * @param int $timeout The maximum time in seconds to wait for user input before timing out.
     *
     * @return string Return the entered password, or an empty string if no password was provided or an error occurred.
     * @ignore
     */
    protected static function getWindowsHiddenPassword(string $message, int $timeout): string
    {
        $vbscript = sys_get_temp_dir() . 'prompt_password.vbs';
        $inputBox = 'wscript.echo(InputBox("'. addslashes($message) . '", "", ""))';

        if ($timeout > 0) {
            $result = self::timeout(static function() {
                self::newLine();
                self::error("Error: Timeout exceeded. No input provided.");
            }, $timeout);

            if ($result === true) {
                return '';
            }
        }

        if (file_put_contents($vbscript, $inputBox) !== false) {
            $password = self::_shell("cscript //nologo " . escapeshellarg($vbscript));
        }

        if (!$password) {
            $password = self::_shell('powershell -Command "Read-Host -AsSecureString | ConvertFrom-SecureString"');
        }

        unlink($vbscript);
        return !$password ? '' : trim($password);
    }

    /**
     * Calculate the visible CLI window width and height.
     *
     * @return void
     * @ignore
     */
    protected static function getVisibleWindow(): void
    {
        if (self::$windowHeight !== null && self::$windowWidth !== null) {
            return;
        }

        if (is_platform('windows')) {
            // Use PowerShell to get console size on Windows
            $size = self::_shell('powershell -command "Get-Host | ForEach-Object { $_.UI.RawUI.WindowSize.Height; $_.UI.RawUI.WindowSize.Width }"');

            if ($size) {
                $dimensions = explode("\n", trim($size));
                if($dimensions !== []){
                    self::$windowHeight = (int) $dimensions[0];
                    self::$windowWidth = (int) $dimensions[1];
                }
            }
        } else {
            // Fallback for Unix-like systems
            $size = self::_exec('stty size');
            if ($size && preg_match('/(\d+)\s+(\d+)/', $size, $matches)) {
                self::$windowHeight = (int) $matches[1];
                self::$windowWidth  = (int) $matches[2];
            }
        }

        // As a fallback, if still not set, default to standard size
        if (self::$windowHeight === null || self::$windowWidth === null) {
            self::$windowHeight = (int) self::_exec('tput lines');
            self::$windowWidth  = (int) self::_exec('tput cols');
        }
    }
}