<?php 
/**
 * Luminova Framework CLI Terminal class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Command;

use \Closure;
use \Luminova\Boot;
use \Luminova\Luminova;
use \Luminova\Command\Novakit;
use \Luminova\Http\Network\IP;
use \Luminova\Security\Validation;
use \Luminova\Exceptions\IOException;
use \Luminova\Command\Consoles\Commands;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Command\Utils\{Text, Color};
use function \Luminova\Funcs\{is_platform, list_to_array};

final class Terminal
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
    private static ?int $windowHeight = null;

    /**
     * Width of terminal visible window
     *
     * @var int|null $windowWidth
     */
    private static ?int $windowWidth = null;

    /**
     * About system information.
     *
     * @var array $session
     */
    private static array $session = [
        'metadata' => null,
        'ppid' => null,
        'model' => null,
        'name' => null,
        'id' => null,
    ];

    /**
     * Is the readline library on the system.
     *
     * @var bool $isReadLine
     */
    public static bool $isReadLine = false;

    /**
     * Is last write a new line.
     *
     * @var bool $isNewLine
     */
    private static bool $isNewLine = true;

    /**
     * Flags to determine if color and ansi are supported 
     * based on different `STDOUT` and `STDERR` resource.
     *
     * @var array{colors:array{0:?bool,1:?bool},ansi:array{0:?bool,1:?bool}} $isSupported
     */
    private static array $isSupported = [
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
     * STD string paths.
     * 
     * @var array<string,string> STDPATH
     */
    private const STDPATH = [
        'STDIN' => 'php://stdin',
        'STDOUT' => 'php://output',
        'STDERR' => 'php://stderr'
    ];

    /**
     * Input validations object.
     * 
     * @var Validation|null $validation;
     */
    private static ?Validation $validation = null;

    /**
     * Command is initialized.
     * 
     * @var bool $isInitialized;
     */
    private static bool $isInitialized = false;

    /**
     * Private constructor to prevent direct instantiation.
     *
     * Use `Terminal::init()` if manual initialization is needed.
     */
    private function __construct(){}

    /**
     * Initialize the terminal instance for CLI operations.
     *
     * - Sets up standard input/output streams.
     * - Detects whether `readline` extension is available.
     * - Checks for color support in the terminal output.
     *
     * Subsequent calls to this method have no effect once initialization is complete.
     *
     * @return void
     *
     * @example - Manual initialization:
     * ```php
     * Terminal::init();
     * ```
     */
    public static function init(): void
    {
        if(self::$isInitialized){
            return;
        }

        self::$isReadLine = extension_loaded('readline');

        Boot::defineCliStreams();
        self::isColorSupported(self::STD_OUT);

        self::$isInitialized = true;
    }

    /**
     * Display an error message box in the console.
     * 
     * The message is formatted as a block with a default red background and white text.
     *
     * @param string $text The error message to display.
     * @param string|null $foreground Optional text color (default: white).
     * @param string|null $background Optional background color (default: red).
     * @param int|null $width Optional width of the block (default: auto).
     *
     * @return void
     * 
     * @example - Example:
     * ```php
     * Terminal::error("Something went wrong!");
     * ```
     */
    public static function error(
        string $text, 
        ?string $foreground = 'white', 
        ?string $background = 'red',
        ?int $width = null
    ): void
    {
        self::card($text, self::STD_ERR, $foreground, $background, $width);
    }

    /**
     * Display a success message box in the console.
     * 
     * The message is formatted as a block with a default green background and white text.
     *
     * @param string $text The success message to display.
     * @param string|null $foreground Optional text color (default: white).
     * @param string|null $background Optional background color (default: green).
     * @param int|null $width Optional width of the block (default: auto).
     *
     * @return void
     * 
     * @example - Example:
     * ```php
     * Terminal::success("Operation completed successfully!");
     * ```
     */
    public static function success(
        string $text, 
        ?string $foreground = 'white', 
        ?string $background = 'green',
        ?int $width = null
    ): void
    {
        self::card($text, self::STD_OUT, $foreground, $background, $width);
    }

    /**
     * Display an informational message in the console.
     * 
     * By default, the text is displayed with a blue foreground and no background.
     *
     * @param string $text The message to display.
     * @param string|null $foreground Optional foreground color (default: blue).
     * @param string|null $background Optional background color (default: none).
     * @param int|null $width Optional width of the block (default: auto).
     *
     * @return void
     * 
     * @example - Example:
     * ```php
     * Terminal::info("Server is starting...");
     * ```
     */
    public static function info(
        string $text, 
        ?string $foreground = 'blue', 
        ?string $background = null,
        ?int $width = null
    ): void
    {
        self::card($text, self::STD_OUT, $foreground, $background, $width);
    }

    /**
     * Print text followed by a newline to the specified stream (default: STDOUT).
     * 
     * Supports optional foreground and background colors.
     *
     * @param string $text The text to write.
     * @param string|null $foreground Optional foreground color.
     * @param string|null $background Optional background color.
     * @param int $stream Stream resource to write to 
     *      (e.g, `Terminal::STD_OUT`, `Terminal::STD_IN`, `Terminal::STD_ERR`).
     *
     * @return void
     *
     * @example - Example:
     * ```php
     * Terminal::writeln("Hello, World!", "green");
     * ```
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
     * Print text without a newline to the specified stream (default: STDOUT).
     * 
     * Supports optional foreground and background colors.
     *
     * @param string $text The text to write.
     * @param string|null $foreground Optional foreground color.
     * @param string|null $background Optional background color.
     * @param int $stream Stream resource to write to 
     *          (e.g, `Terminal::STD_OUT`, `Terminal::STD_IN`, `Terminal::STD_ERR`).
     *
     * @return void
     *
     * @example - Example:
     * ```php
     * Terminal::write("Loading...", "yellow");
     * ```
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
     * Print text directly to the console using echo.
     * 
     * Optional colors can be applied for foreground and background.
     *
     * @param string $text The text to print.
     * @param string|null $foreground Optional foreground color.
     * @param string|null $background Optional background color.
     *
     * @return void
     *
     * @example - Example:
     * ```php
     * Terminal::print("Processing...", "cyan");
     * ```
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

        self::$isNewLine = false;
        echo $text;
    }

    /**
     * Write text directly to a stream resource without applying colors.
     * 
     * Falls back to echo if the environment is non-command-based.
     *
     * @param string $text The text to write.
     * @param resource|int $handler Stream resource (STDOUT, STDERR, STDIN, or custom).
     *
     * @return void
     *
     * @example - Example:
     * ```php
     * Terminal::fwrite("Log message\n", STDERR);
     * ```
     */
    public static function fwrite(string $text, mixed $handler = self::STD_OUT): void
    {
        if (!Luminova::isCommand()) {
            echo $text;
            return;
        }

        $handler = self::getStd($handler);
        fwrite($handler, $text);

        if (
            is_resource($handler) &&
            !in_array($handler, [STDOUT, STDERR, STDIN], true) && 
            !@stream_isatty($handler)
        ) {
            @fclose($handler);
        }
    }

    /**
     * Displays a countdown timer in the console.
     * 
     * This method shows a countdown with a custom message, 
     * or prompts the user to press any key to continue if the timer is 0 or negative.
     *
     * @param int $seconds Number of seconds to wait before continuing (default: 0).
     * @param string $pattern Message pattern displayed during the countdown (default: 'Waiting...(%d seconds)'). 
     *                        Use '%d' as a placeholder for the remaining seconds.
     *
     * @return void
     *
     * > **Note:** 
     * > - The console screen will be cleared during the countdown.
     * > - If $seconds is less than 1, it displays 'Press any key to continue...' and waits for input.
     *
     * @example - Simple countdown:
     * ```php
     * Terminal::waiting(5);
     * ```
     *
     * @example - Custom message pattern:
     * ```php
     * Terminal::waiting(3, 'Hold on... %d sec remaining');
     * ```
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
     * Pauses execution for a specified number of seconds, optionally clearing the screen during the freeze.
     *
     * @param int $seconds Number of seconds to pause execution (default: 10).
     * @param bool $clear Whether to clear the console screen during the freeze (default: true).
     *
     * @return void
     *
     * @example - Pause without clearing screen:
     * ```php
     * Terminal::freeze(5, false);
     * ```
     *
     * @example - Pause with screen cleared every second:
     * ```php
     * Terminal::freeze(3, true);
     * ```
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
     * The spinner cycles through a set of characters to create a simple loading animation.
     * You can customize the spinner frames, the number of rotations, the speed, and
     * optionally execute a callback or print "Done!" when finished.
     *
     * @param array<int,string> $spinners Characters representing each frame of the spinner animation.
     *                                    Default: ['-', '\', '|', '/'].
     * @param int $spins Number of full rotations through the spinner array (default: 10).
     * @param int $sleep Delay in microseconds between frames to control animation speed (default: 100_000 = 0.1s).
     * @param (Closure():void)|bool|null $onComplete Callback or flag executed after completion.
     *        - If `true`, prints "Done!\n".
     *        - If a `Closure`, executes the callback.
     *        - If `null`, does nothing.
     *
     * @return void
     *
     * @example - Basic spinner:
     * ```php
     * Terminal::spinner();
     * ```
     *
     * @example - Custom spinner frames and speed:
     * ```php
     * Terminal::spinner(
     *     spinners: ['⠁','⠂','⠄','⡀','⢀','⠠','⠐','⠈'],
     *     spins: 20,
     *     sleep: 50000
     * );
     * ```
     *
     * @example - Spinner with a completion callback:
     * ```php
     * Terminal::spinner(onComplete: function() {
     *     echo "Task completed!\n";
     * });
     * ```
     */
    public static function spinner(
        array $spinners = ['-', '\\', '|', '/'], 
        int $spins = 10, 
        int $sleep = 100_000, 
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
     * Displays a progress bar in the console for a given step out of a total.
     *
     * @param int|false $step Current step of the progress (1-based). 
     *              Set to `false` to mark completion.
     * @param int $steps Total number of steps for the progress (default: 10).
     * @param bool $beep Whether to beep when progress completes (default: true).
     *
     * @return float|int Returns the completion percentage (0-100). Returns 100 if completed.
     *
     * @example - Basic usage:
     * ```php
     * for ($i = 1; $i <= 10; $i++) {
     *     Terminal::progress($i, 10);
     *     usleep(200000);
     * }
     * ```
     *
     * @example - Mark as complete manually:
     * ```php
     * Terminal::progress(false, 10); // prints 100% completion bar
     * ```
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
     * Displays a progress bar and executes optional callbacks at each step and upon completion.
     *
     * Handles iteration internally and provides hooks for progress and finish events.
     * Useful for visual feedback during long-running tasks.
     *
     * @param int $limit Total number of progress steps.
     * @param Closure|null $onFinish Optional callback executed once when progress reaches 100%.
     * @param Closure|null $onProgress Optional callback executed on each progress step with the current percentage.
     * @param bool $beep Whether to emit a beep upon completion (default: true).
     *
     * @return void
     *
     * @example - Simple usage:
     * ```php
     * Terminal::watcher(5);
     * ```
     *
     * @example - With progress callback:
     * ```php
     * Terminal::watcher(10, onProgress: function(int $percent) {
     *     echo "Progress: $percent%\n";
     * });
     * ```
     *
     * @example - With finish callback:
     * ```php
     * Terminal::watcher(10, onFinish: function() {
     *     echo "Task completed!\n";
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
     * Prompt the user to type input with optional validation and selectable options.
     *
     * You can optionally pass an array of options to suggest possible values.
     * Each option can also have a color if supported (e.g., `['green' => 'YES', 'red' => 'NO']`).
     * Validation rules can restrict allowed values or be disabled entirely.
     *
     * @param string $message The message to prompt the user with.
     * @param array<string|int,string|int> $options Optional list of suggested options.
     * @param string|bool|null $validations Optional validation rules:
     *      - null (default): automatically uses required|in_array(options)
     *      - false: disables validation
     *      - string: custom validation rules
     * @param bool $silent Whether to suppress validation failure messages (default: false)
     *
     * @return string Returns the user's input after validation.
     * @see self::input()
     * @see self::read()
     *
     * @example - Basic usage:
     * ```php
     * $name = Terminal::prompt('Enter your name');
     * ```
     *
     * @example - With options and color:
     * ```php
     * $answer = Terminal::prompt(
     *     'Do you want to continue?', 
     *     ['green' => 'YES', 'red' => 'NO']
     * );
     * ```
     *
     * @see https://luminova.ng/docs/0.0.0/security/validation
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
        $rules = (($validations === 'none')
            ? false 
            : (($rules !== [] && is_array($rules))
                ? "required|in_array([" .  implode(",", $rules) . "])" 
                : $rules)
            );

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
     * Display a multiple-choice selection prompt in the terminal.
     *
     * Users select options using index numbers. Supports single or multiple selection.
     * - If `$required` is true, at least one option must be selected.
     * - `$multiChoice` allows selecting multiple options separated by commas.
     *
     * @param string $text The message describing the choice.
     * @param array<string|int,mixed> $options The list of selectable options.
     *      Can be indexed or associative array (keys will still be shown as selection indexes).
     * @param bool $required Whether the user must select at least one option (default: false).
     * @param bool $multiChoice Allow multiple selections separated by commas (default: true).
     *
     * @return array<string|int,mixed> Returns an array of selected keys and their corresponding values.
     *
     * @throws IOException If `$options` is empty or invalid.
     *
     * @example - Single choice:
     * ```php
     * $gender = Terminal::chooser(
     *      'Select gender:', 
     *      [
     *          'male' => 'Male', 
     *          'female' => 'Female'
     *      ], 
     *      required: true, 
     *      multiChoice: false
     * );
     * ```
     *
     * @example - Multiple choices:
     * ```php
     * $fruits = Terminal::chooser('Select your favorite fruits:', ['Apple','Banana','Cherry']);
     * ```
     */
    public static function chooser(
        string $text, 
        array $options, 
        bool $required = false,
        bool $multiChoice = true
    ): array
    {
        if ($options == []) {
            throw new IOException('Invalid argument, $options is required for chooser.');
        }

        $lastIndex = 0;
        $placeholder = $multiChoice 
            ? 'To specify multiple values, separate them with commas.'
            : '';
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
        
        $rules = $required ? "required|key_exist([" . rtrim($rules, ',')  . "])" : 'nullable';
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

            if(!$multiChoice && str_contains($input, ',')){
                $input = null;
                self::fwrite('Multiple options is not allowed, select one option to continue.');
                self::newLine();
            }
        } while ($required && ($input === null) ? true : !self::validate($input, ['input' => $rules]));

        return self::getInputValues($multiChoice 
            ? list_to_array($input) 
            : [$input], 
            $optionValues
        );
    }

    /**
     * Prompt the user to enter a password securely (input hidden).
     *
     * Works cross-platform:
     * - Windows: uses PowerShell or VBS to hide input.
     * - Linux/macOS: uses terminal raw input to hide password.
     *
     * Supports retry attempts, optional empty passwords, and optional timeout.
     *
     * @param string $message Optional prompt message (default: 'Enter Password').
     * @param bool $allowEmptyPassword Whether empty passwords are permitted (default: false).
     * @param int $retry Number of retry attempts, 0 for unlimited (default: 3).
     * @param int $timeout Optional timeout in seconds for input, 0 for no timeout (default: 0).
     *
     * @return string Returns the entered password, or empty string if max retries exceeded.
     *
     * @example - Basic password input:
     * ```php
     * $password = Terminal::password();
     * ```
     *
     * @example - With unlimited retries:
     * ```php
     * $password = Terminal::password('Enter Admin Password', allowEmptyPassword: false, retry: 0);
     * ```
     *
     * @example - With timeout (10 seconds):
     * ```php
     * $password = Terminal::password('Enter your key', timeout: 10);
     * ```
     */
    public static function password(
        string $message = 'Enter Password', 
        bool $allowEmptyPassword = false,
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
    
            if ($password !== '' || $allowEmptyPassword) {
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
     * Read a single line of user input from the terminal or piped input.
     *
     * If $default is null, input is required and the prompt repeats until a value is entered.
     * Supports optional prompts, default values, and a separate STDIN stream handle.
     * For multi-line input, use {@see self::read()} instead.
     *
     * @param string|null $prompt Optional message displayed before reading input.
     * @param string|null $default Default value returned if input is empty. Null = required input.
     * @param bool $newStream Open a new STDIN stream as a separate handle (default: false).
     *
     * @return string Returns the trimmed input string or the default value.
     */
    public static function input(
        ?string $prompt = null,
        ?string $default = '',
        bool $newStream = false
    ): string 
    {
        while (true) {
            $input = null;

            if (self::$isReadLine && ENVIRONMENT !== 'testing') {
                $input = @readline($prompt) ?: null;
            } elseif ($prompt !== null) {
                self::print($prompt);
            }

            if ($input === null) {
                $input = self::readFromInput($newStream);
            }

            if ($input !== null && $input !== '') {
                return trim($input);
            }

            if ($default !== null) {
                return $default;
            }

            // Required input: loop until user enters something
        }
    }

    /**
     * Read input from STDIN, supporting single or multi-line input.
     *
     * Suitable for piped data or long content. For interactive prompts or single-line input,
     * use {@see self::input()} instead.
     * 
     * When `$eof` is true, the entire STDIN stream is read until EOF.
     * When `$eof` is false, a single line is read.
     * 
     * Alias {@see self::readInput()}
     *
     * @param string $default Default value returned if reading fails or input is empty.
     * @param bool $eof Read until EOF if true, otherwise single line (delegates to {@see self::input()}).
     * @param bool $newStream Open a new STDIN stream as a separate handle (default: false).
     *
     * @return string Returns trimmed input string or default if empty/failure.
     * @example - In CLI:
     * 
     * ```bash
     * echo "Log Message From Pipe" | php index.php logger
     * ```
     * 
     * ```bash
     * php index.php list-something | php index.php logger
     * ```
     * @example - In PHP (Read From Pipe Input):
     * ```php
     * $data = Terminal::read();
     * ```
     */
    public static function read(string $default = '', bool $eof = true, bool $newStream = false): string
    {
        if (!$eof) {
            return self::input(default: $default, newStream: $newStream);
        }

        $handle = $newStream ? @fopen(self::STDPATH['STDIN'], 'rb') : STDIN;

        if (!$handle) {
            return $default;
        }

        $input = stream_get_contents($handle);

        if ($newStream && is_resource($handle)) {
            fclose($handle);
        }

        return ($input === false || trim($input) === '') ? $default : trim($input);
    }

    /**
     * Displays a selectable list in the terminal with keyboard navigation.
     *
     * Users can navigate the options using:
     * - Arrow keys (up/down)
     * - Tab / Shift+Tab
     * - Enter to select
     * - Escape to cancel (returns empty string)
     * - Ctrl+C to exit
     *
     * The selected option is returned as a string. Supports optional placeholder text,
     * foreground and background colors for the highlighted selection, and clearing the screen after selection.
     *
     * @param array<int,string|int> $options The list of selectable options.
     * @param int $default The default selected index (0-based).
     * @param string|null $placeholder Optional placeholder text to display above the list (default: `null`).
     * @param bool $clearOnSelect Whether to clear the terminal after selection (default: true).
     * @param string $foreground Foreground color for the highlighted option (default: 'green').
     * @param string|null $background Optional background color for the highlighted option.
     *
     * @return string Returns the selected option as a string, or empty string if cancelled.
     *
     * @example - Basic usage:
     * ```php
     * $choice = Terminal::tablist(['Apple', 'Banana', 'Cherry']);
     * 
     * Terminal::writeln("You selected: $choice\n");
     * ```
     *
     * @example With placeholder and colors:
     * ```php
     * $choice = Terminal::tablist(
     *     ['Red', 'Green', 'Blue'], 
     *     default: 1, 
     *     placeholder: 'Select a color:', 
     *     foreground: 'white', 
     *     background: 'blue'
     * );
     * Terminal::writeln("You picked: $choice");
     * ```
     */
    public static function tablist(
        array $options, 
        int $default = 0,
        ?string $placeholder = null,
        bool $clearOnSelect = true,
        string $foreground = 'green', 
        ?string $background = null
    ): string 
    {
        if($options === []){
            return '';
        }

        self::cursorVisibility(false);
        $totalOptions = count($options);
        $index = ($default >= 0 && $default < $totalOptions) ? $default : 0;
        $value = null;

        while ($value === null) {
            self::writeln(self::tablistUpdate($options, $index, $foreground, $background, $placeholder));
            switch (Keyboard::capture()['name']) {
                case Keyboard::UP:
                case Keyboard::SHIFT_TAB:
                    $index = ($index - 1 + $totalOptions) % $totalOptions;
                    break;
                case Keyboard::DOWN:
                case Keyboard::TAB:
                    $index = ($index + 1) % $totalOptions;
                    break;
                case Keyboard::ENTER:
                    self::cursorVisibility(true);
                    $value = $options[$index] ?? '';
                    break;
                case Keyboard::ESCAPE: 
                    self::cursorVisibility(true);
                    $clearOnSelect = true;
                    $value = '';
                    break;
                case Keyboard::CTRL_C:
                    self::cursorVisibility(true);
                    self::clear();
                    exit(0);
            }
        }

        if($clearOnSelect){
            self::clear();
        }

        return (string) $value;
    }

    /**
     * Generate and print a formatted table structure to the console.
     *
     * Each row is an associative array where the keys match the column headers.
     * Supports optional text coloring for headers, body, and borders, as well as controlling
     * whether borders are shown and whether newlines within cells are retained.
     *
     * @param array<int,string> $headers Column headers for the table.
     * @param array<int,array<string,string>> $rows Table rows where each row is an associative array 
     *          with keys matching headers.
     * @param string|null $foreground Optional text color for table body (default: null).
     * @param string|null $headerColor Optional text color for table headers (default: null).
     * @param string|null $borderColor Optional text color for table borders (default: null).
     * @param bool $border Whether to display borders between rows and columns (default: true).
     * @param bool $shouldRetainNewlines Whether to keep newlines inside cells (default: false).
     *
     * @return string Returns the formatted table as a string, ready for console output.
     *
     * @example - Simple table:
     * ```php
     * Terminal::print(Terminal::table(
     *     ['Name', 'Email'],
     *     [
     *         ['Name' => 'Peter', 'Email' => 'peter@example.com'],
     *         ['Name' => 'Hana', 'Email' => 'hana@example.com']
     *     ]
     * ));
     * ```
     *
     * @example - Colored table with no borders:
     * ```php
     * Terminal::print(Terminal::table(
     *     ['Name', 'Email'],
     *     [
     *         ['Name' => 'Alice', 'Email' => 'alice@example.com'],
     *         ['Name' => 'Bob', 'Email' => 'bob@example.com']
     *     ],
     *     foreground: 'cyan',
     *     headerColor: 'yellow',
     *     border: false
     * ));
     * ```
     */
    public static function table(
        array $headers, 
        array $rows, 
        ?string $foreground = null,
        ?string $headerColor = null, 
        ?string $borderColor = null,
        bool $border = true,
        bool $shouldRetainNewlines = false
    ): string
    {
        $widths = self::getTableHeaderWidths($headers, $rows);
        $heights = self::getTableHeaderHeights($headers, $rows, $widths, $shouldRetainNewlines);
        $verticalBorder = Color::style(self::getTableCorner('vertical'), $borderColor);

        $table = '';
        $table .= self::tBorder($widths, 'topLeft', 'topConnector', 'topRight', $borderColor);
        $table .= self::trow($headers, $widths, $headerColor ?? $foreground, $verticalBorder, true);
        $table .= self::tBorder($widths, 'leftConnector', 'crossings', 'rightConnector', $borderColor);

        foreach ($rows as $index => $row) {
            $height = $heights[$index];
            $lines = array_map(
                fn($header) => Text::lines(Text::wrap(
                    (string) $row[$header] ?? '', 
                    $widths[array_search($header, $headers)]
                )),
                $headers
            );

            for ($lIdx = 0; $lIdx < $height; $lIdx++) {
                $tData = $verticalBorder;

                foreach ($headers as $i => $header) {
                    $value = $lines[$i] ?? [];
                    $hWidth = $widths[$i];

                    $tRow = ' ' . Color::style(str_pad(
                        $shouldRetainNewlines ? ($value[$lIdx] ?? '') : implode(' ', $value),
                        $hWidth
                    ), 
                    $foreground);

                    $width = Text::strlen($tRow);

                    if($hWidth > $width){
                        $tRow .= Text::padding('', $hWidth - $width, Text::RIGHT);
                    }

                    $tData .= $tRow;
                    $tData .= ' ' . $verticalBorder;
                }

                $table .= $tData . PHP_EOL;
            }
    
            if ($border && $index < count($rows) - 1) {
                $table .= self::tBorder(
                    $widths, 
                    'leftConnector', 
                    'crossings', 
                    'rightConnector', 
                    $borderColor
                );
            }
        }
    
        $table .= self::tBorder(
            $widths, 
            'bottomLeft', 
            'bottomConnector', 
            'bottomRight', 
            $borderColor
        );
    
        return $table;
    }

    /**
     * Prints formatted help information for CLI commands.
     *
     * Accepts command metadata and renders descriptions, usages, options,
     * and examples in a readable CLI layout. When `$all` is enabled, help
     * output is grouped and labeled per command.
     *
     * If `$helps` is null, all registered commands are loaded automatically.
     *
     * @param array|null $helps Command metadata, usually controller protected properties.
     * @param bool $all Whether to print help for all commands or a single command.
     *
     * @return void
     * @internal Used by the router to render controller help output.
     */
    public static function helper(?array $helps, bool $all = false): void
    {
        $leftPadding = Text::padding('', 3, Text::LEFT);
        $helps = ($helps === null) 
            ? Commands::getCommands() 
            : ($all ? $helps : [$helps]);

        foreach($helps as $name => $properties){
            if(!$properties){
                continue;
            }

            if($all){
                self::newLine();
                $head = Color::apply("------[{$name} Help Information]------", Text::FONT_BOLD, 'brightCyan');
                self::writeln($head);
            }

            if(!is_array($properties)){
                continue;
            }

            $total = count($properties);
            $index = 0;

            foreach($properties as $key => $value){
                if($key === 'users' || $key === 'authentication'){
                    continue;
                }

                if(is_array($value)){
                    if(in_array($key, ['usages', 'examples', 'options'], true)){
                        self::printHelp($value, $key, $leftPadding);
                    }
                }elseif($key === 'usages' || $key === 'description'){
                    self::writeln(ucfirst($key) . ':', 'lightYellow');
                    self::writeln($leftPadding . trim($value));
                }

                if($index < $total - 1){
                    self::newLine();
                }

                $index++;
            }
        }
    }

    /**
     * Displays system and environment information in a table format.
     *
     * Intended for CLI usage, this method outputs details such as
     * PHP version, operating system, terminal size, and other
     * runtime-related information.
     *
     * @return void
     */
    public static function about(): void 
    {
        self::writeln(self::table(
            ['Name', 'Value'], 
            self::getSystemInfo()
        ));
    }

    /**
     * Prints the NovaKit CLI header banner.
     *
     * Displays framework version, CLI tool version, and current server time.
     * The header is skipped if the `--no-header` argument is present.
     *
     * @return bool Returns true if the header was printed, false if suppressed.
     */
    public static function header(): bool
    {
        if(self::hasArgument('--no-header')){
            return false;
        }

        self::writeln(sprintf(
            'PHP Luminova v%s NovaKit Command Line Tool v%s - Server Time: %s UTC%s',
            Luminova::VERSION,
            Luminova::NOVAKIT_VERSION,
            date('Y-m-d H:i:s'),
            date('P')
        ), 'green');
        return true;
    }

    /**
     * Returns the system user executing the current script.
     *
     * Attempts to resolve the actual OS-level user using a shell command.
     * If unavailable, falls back to PHP's process user.
     *
     * @return string The current system username with whitespace removed.
     */
    public static function whoami(): string
    {
        $user = self::_shell(is_platform('windows') ? 'echo %USERNAME%' : 'whoami');
    
        if (!$user) {
            $user = get_current_user();
        }

        return trim($user);
    }

    /**
     * Detect the PHP CLI executable path.
     *
     * This method attempts to locate a working PHP CLI binary using multiple strategies:
     * 1. The `PHP_BINARY` constant (the currently running PHP binary).
     * 2. The `which php` shell command (Linux/macOS).
     * 3. Common default PHP paths for Linux, macOS, and Windows environments.
     *
     * When `$test` is `true`, each candidate is verified by running `php -v` to ensure it is functional.
     *
     * @param bool $test If true, test each candidate binary to ensure it runs (default: true).
     * 
     * @return string|null The full path to a usable PHP CLI executable, or null if none could be detected.
     */
    public static function whichPhp(bool $test = true): ?string
    {
        $candidates = [];

        if (defined('PHP_BINARY') && PHP_BINARY !== '') {
            $candidates[] = PHP_BINARY;
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            $output = null;
            $returnVar = null;
            @exec('which php', $output, $returnVar);
            
            if ($returnVar === 0 && !empty($output)) {
                $candidates[] = $output[0];
            }
        }

        $candidates += match(true){
            (PHP_OS_FAMILY === 'Darwin') => [
                'php',
                '/usr/bin/php',
                '/usr/local/bin/php',
                '/usr/local/php/bin/php',
                '/opt/homebrew/bin/php',
                '/Applications/XAMPP/xamppfiles/bin/php',
            ],
            (PHP_OS_FAMILY === 'Linux') => [
                'php',
                '/usr/bin/php',
                '/usr/local/bin/php',
            ],
            (PHP_OS_FAMILY === 'Windows') => [
                'C:\\xampp\\php\\php.exe',
                'C:\\wamp\\bin\\php\\php.exe',
                'C:\\wamp\\bin\\php\\php8.1.0\\php.exe',
                'C:\\wamp\\bin\\php\\php8.0.0\\php.exe',
                'C:\\php\\php.exe',
            ],
            default => ['php']
        };

        foreach ($candidates as $php) {
            if (!is_file($php) || !is_executable($php)) {
                continue;
            }

            if(!$test){
                return $php;
            }

            $ret = null;
            @exec(escapeshellarg($php) . ' -v', $out, $ret);
            if ($ret === 0) {
                return $php;
            }
        }

        return null;
    }

    /**
     * Terminates script execution immediately, optionally displaying a message and providing an exit code.
     *
     * @param string|null $message Optional message to display before exiting.
     * @param int $exitCode Exit status code (default: `STATUS_SUCCESS`).
     *
     * @return never
     *
     * @example - Terminate with a message and default success code:
     * ```php
     * Terminal::terminate('Operation completed successfully.');
     * ```
     *
     * @example - Terminate with a custom exit code:
     * ```php
     * Terminal::terminate('Fatal error occurred.', 1);
     * ```
     */
    public static function terminate(?string $message = null, int $exitCode = STATUS_SUCCESS): void
    {
        if ($message !== null) {
            self::writeln($message);
        }

        exit($exitCode);
    }

    /**
     * Highlights a URL in the terminal to indicate it is clickable.
     *
     * If ANSI hyperlinks are supported, the URL will be clickable. Otherwise, it will be underlined.
     *
     * @param string $url The URL to highlight.
     * @param string|null $title Optional title to display instead of the URL (default: null).
     *
     * @return void
     *
     * @example - Example:
     * ```php
     * Terminal::link('https://luminova.ng');
     * Terminal::link('https://luminova.ng', 'Luminova Website');
     * ```
     */
    public static function link(string $url, ?string $title = null): void 
    {
        $title ??= $url;
        self::write(self::isAnsiSupported() 
            ? "\033]8;;{$url}\033\\{$title}\033]8;;\033\\" 
            : "\033[4m{$url}\033[0m"
        );
    }

    /**
     * Executes a callback function if no activity is detected on the specified stream within a timeout.
     *
     * @param Closure $callback Callback function to execute on timeout.
     * @param int $timeout Timeout in seconds. If <= 0, the callback is executed immediately (default: 0).
     * @param resource|string|int $stream Optional stream to monitor for activity (default: STDIN).
     * 
     * @return bool Returns true if the timeout occurred and the callback was executed, false otherwise.
     *
     * @example - Example:
     * ```php
     * Terminal::timeout(fn() => echo "No input detected!\n", 5);
     * ```
     */
    public static function timeout(Closure $callback, int $timeout = 0, mixed $stream = self::STD_IN): bool
    {
        if ($timeout <= 0) {
            $callback();
            return true;
        }

        $write = null;
        $except = null;
        $read = [self::getStd($stream)];
        $result = stream_select($read, $write, $except, $timeout);
        
        if ($result === false || $result === 0) {
            $callback();
            return true;
        }

        return false;
    }

    /**
     * Execute a system command and return its output as an array of lines.
     *
     * @param string $command The command to execute.
     * 
     * @return array|false Returns the output lines of the command on success, or false on failure.
     *
     * @example - Example:
     * ```php
     * $output = Terminal::execute('ls -la');
     * if ($output !== false) {
     *     print_r($output);
     * }
     * ```
     */
    public static function execute(string $command): array|bool
    {
        $code = STATUS_ERROR;
        $output = [];

        exec($command, $output, $code);
        
        if ($code === STATUS_SUCCESS) {
            return $output;
        }

        return false;
    }

    /**
     * Emits a beep (bell) sound in the terminal a specified number of times.
     *
     * Useful for notifications, alerts, or signaling the user in CLI scripts.
     *
     * @param int $total Number of times to beep (default: 1).
     *
     * @return void
     * 
     * @example - Multiple beeps:
     * ```php
     * Terminal::beeps(3);
     * ```
     */
    public static function beeps(int $total = 1): void
    {
        self::print(str_repeat("\x07", $total));
    }

    /**
     * Display an error message for an unknown command.
     *
     * Prints a formatted "command not found" message to STDERR.
     *
     * @param string $command The executed command.
     * @param string|null $color Optional text color for the command name (default: red).
     *
     * @return int Always returns STATUS_ERROR.
     */
    public static function oops(string $command, ?string $color = 'red'): int 
    {
        $command = "'{$command}'";
        $command = self::isColorSupported() 
            ? Color::style($command, $color) 
            : $command;

        self::fwrite("Unknown command {$command} not found." . PHP_EOL, self::STD_ERR);
        return STATUS_ERROR;
    }

    /**
     * Show or hide user input in the terminal.
     *
     * Useful for password prompts or sensitive input.
     *
     * @param bool $visibility True to show input, false to hide it.
     *
     * @return bool Returns true on success, false on failure.
     */
    public static function visibility(bool $visibility = true): bool
    {
        $command = is_platform('windows') 
            ? ($visibility ? 'echo on' : 'echo off')
            : ($visibility ? 'stty echo' : 'stty -echo');

        return self::_shell($command) !== null;
    }

    /**
     * Clear the terminal screen.
     *
     * Supported modes:
     * - default: Clear the entire screen.
     * - partial: Clear from cursor to bottom.
     * - full: Clear screen and scroll-back buffer.
     *
     * @param string $mode Clearing mode (default: "default").
     *
     * @return void
     */
    public static function clear(string $mode = 'default'): void
    {
        if (is_platform('windows') && !self::isStreamSupports('sapi_windows_vt100_support', self::STD_OUT)) {
            if (self::_shell('cls')) {
                return;
            }

            self::newLine(self::getHeight(40));
            return;
        }

        $sequences = [
            'default' => "\033[H\033[2J",
            'partial' => "\033[J",
            'full'    => "\033[H\033[2J\033[3J"
        ];
            
        self::fwrite($sequences[$mode] ?? $sequences['default']);
    }

    /**
     * Remove the last printed line(s) from the terminal output.
     *
     * If no output is provided, clears the previous line.
     * If output is provided, clears the exact number of wrapped lines.
     *
     * @param string|null $lastOutput Previously printed output (optional).
     *
     * @return void
     */
    public static function flush(?string $lastOutput = null): void
    {
        if (is_platform('windows') && !self::isStreamSupports('sapi_windows_vt100_support', self::STD_OUT)) {
            self::fwrite("\r");
            return;
        }

        if ($lastOutput === null) {
            self::fwrite("\033[1A\033[2K");
            return;
        }

        $lines = explode(
            PHP_EOL,
            wordwrap($lastOutput, self::getWidth(), PHP_EOL, true)
        );

        foreach ($lines as $_) {
            self::fwrite("\033[1A\033[2K");
        }
    }

    /**
     * Validate user input against a set of rules, typically used for prompts.
     *
     * @param string $value The input value to validate.
     * @param array $rules Validation rules to apply (e.g., `required|in_array([yes,no])`).
     * 
     * @return bool Returns true if validation passes, false otherwise.
     *
     * @example - Example:
     * ```php
     * $input = Terminal::prompt('Continue?');
     * 
     * if (!Terminal::validate($input, ['input' => 'required|in_array([yes,no])'])) {
     *     echo "Invalid input!";
     * }
     * ```
     */
    public static function validate(string $value, array $rules): bool
    {
        $field = ['input' => $value]; 
        self::$validation ??= new Validation();
        self::$validation->setRules($rules)->setBody($field);
    
        if (!self::$validation->validate()) {
            self::error(self::$validation->getError('input'));
            return false;
        }

        return true;
    }

    /**
     * Escape a command-line argument to ensure it is safely executed in the shell.
     *
     * Handles special characters differently for Windows and Unix-like platforms.
     *
     * @param string|null $argument The argument to escape.
     * 
     * @return string Returns the safely escaped string ready for shell execution.
     *
     * @example - Example:
     * ```php
     * $safeArg = Terminal::escape('file with spaces.txt');
     * exec("cat $safeArg");
     * ```
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
            return '"' . str_replace(
                ['"', '^', '%', '!', "\n"], 
                ['""', '"^^"', '"^%"', '"^!"', '!LF!'], 
                preg_replace('/(\\\\+)$/', '$1$1', $argument)
            ) . '"';
        }

        return $argument;
    }

    /**
     * Replace placeholders in a command string with values from an environment array.
     *
     * Placeholders must follow the format `${:VAR_NAME}`, where `VAR_NAME` corresponds to a key in `$env`.
     * Optionally, replacements can be escaped for safe shell execution.
     *
     * @param string $command The command containing placeholders (e.g., `echo ${:USER}`).
     * @param array<string,mixed> $env Associative array mapping placeholder names to values.
     * @param bool $escape Whether to escape each replacement for shell safety (default: false).
     * 
     * @return string Returns the command with all placeholders replaced.
     * @throws IOException If a placeholder is missing in `$env` or its value is `false`.
     *
     * @example - Example:
     * ```php
     * $cmd = "echo ${:USER} is logged in";
     * $env = ['USER' => 'Alice'];
     * 
     * $safeCommand = Terminal::replace($cmd, $env);
     * // Output: "echo Alice is logged in"
     *
     * $safeCommandEscaped = Terminal::replace($cmd, $env, true);
     * // Output: "echo 'Alice' is logged in" on Unix
     * ```
     */
    public static function replace(string $command, array $env, bool $escape = false): string
    {
        return preg_replace_callback(
            '/\$\{:([_a-zA-Z][\w]*)\}/', 
            function ($matches) use ($command, $env, $escape) {
                $key = $matches[1];

                if (!array_key_exists($key, $env)) {
                    throw new IOException(sprintf(
                        'Missing value for parameter "%s" in command: %s',
                        $key, $command
                    ));
                }

                return $escape ? self::escape($env[$key]) : $env[$key];
            }, 
            $command
        );
    }

    /**
     * Controls the cursor visibility in the terminal.
     *
     * @param bool $showCursor Set to true to show the cursor, false to hide it.
     * @return void
     */
    public static function cursorVisibility(bool $showCursor):  void
    {
        self::print($showCursor ? "\033[?25h" : "\033[?25l");
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
     * Resolve a Terminal stream identifier to its PHP global stream.
     *
     * Maps `Terminal::STD_OUT`, `Terminal::STD_ERR`, and `Terminal::STD_IN`
     * to their corresponding PHP predefined streams (`STDOUT`, `STDERR`, `STDIN`).
     * Any other value is returned as-is, allowing custom stream handlers.
     *
     * @param resource|int $std A Terminal stream constant or a custom stream value.
     *
     * @return resource|mixed Returns the resolved PHP stream resource, or the original value
     *                        if it is not a recognized Terminal stream constant.
     */
    public static function getStd(mixed $std): mixed
    {
        return match ($std) {
            self::STD_OUT => STDOUT,
            self::STD_ERR => STDERR,
            self::STD_IN  => STDIN,
            default       => $std,
        };
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
        $devNull = is_platform('windows') ? ' 2>NUL' : ' 2>/dev/null';
        if(str_contains($command, $devNull)){
            $devNull = '';
        }

        return exec("{$command}{$devNull}", $output, $result_code);
    }

    /**
     * Executes a shell command via `shell_exec` and return the complete output as a string.
     * Also it redirects error output to null based on the platform (Windows or Unix-like).
     * 
     * @param string $command The command to execute.
     * 
     * @return string|null Return the output of the command, or null on error.
     */
    public static function _shell(string $command): ?string
    {
        $devNull = is_platform('windows') ? ' 2>NUL' : ' 2>/dev/null';

        if(str_contains($command, $devNull)){
            $devNull = '';
        }

        $response = shell_exec("{$command}{$devNull}");

        if(!$response){
            return null;
        }

        return $response;
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
     * 
     * @internal Pass raw command arguments from $_SERVER['argv'].
     */
    public static function parseCommands(array $arguments, bool $controller = false): array
    {
        $caller = $arguments[0] ?? '';
        $result = [
            'group' => '',
            'name'  => '',
            'arguments' => [],
            'options' => [],
            'command' => '',
            'input' => self::toString($arguments),
        ];

        if ($caller === 'novakit' || $caller === 'php' || preg_match('/^.*\.php$/', $caller)) {
            array_shift($arguments); //Remove the front controller file
            $result['command'] = implode(' ', $arguments);
            $command = $arguments[0] ?? '';

            // php index.php group name
            // php novakit group --foo

            if($controller){
                $result['group'] = $command; 
                $result['name'] = $arguments[1] ?? '';
            }else{
                $pos = strpos($command, ':');
                $result['group'] = ($pos === false) ? $command : substr($command, 0, $pos); 
                $result['name'] = $command;
            }
        }else{
            $hasSpace = array_reduce(
                $arguments, 
                fn($carry, $item) => $carry || str_contains($item, ' '), 
                false
            );
            $callerCommend = $arguments;

            if ($hasSpace) {
                $callerCommend = implode(' ', $arguments);
                $callerCommend = $callerCommend[0];
            }
            
            $result['command'] = $callerCommend;
            $result['name'] = $arguments[1] ?? '';
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
    public static function extract(array $arguments, $controller = false): array
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

        if (!self::isColorDisabled()) {
            if (is_platform('windows')) {
                return self::$isSupported['colors'][$std] = getenv('WT_SESSION') || self::isWindowsTerminal($std);
            }

            return self::$isSupported['colors'][$std] = (int) trim(@self::_exec('tput colors')) > 0;
        }

        return self::$isSupported['colors'][$std] = false;
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
            self::hasArgument('--no-color') || 
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
            self::hasArgument('--no-ansi') || 
            isset($_SERVER['DISABLE_ANSI']) || 
            getenv('DISABLE_ANSI') !== false
        );
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

        $term = strtolower(getenv('TERM_PROGRAM') ?: (getenv('TERM') ?: ''));

        if ($term) {
            return $macResult = in_array($term, ['hyper', 'apple_terminal', 'xterm-256color']) || 
                ($term === 'iterm' && version_compare((string) getenv('TERM_PROGRAM_VERSION'), '3.4', '>='));
        }

        return $macResult = false;
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

        $term = getenv('TERM_PROGRAM') ?:  getenv('TERM');

        if($term !== false){
            return $unixResult = in_array(
                strtolower($term), 
                ['xterm', 'linux', 'xterm-256color', 'gnome-terminal', 'konsole', 'terminator']
            );
        }
        return $unixResult = false;
    }

    /**
     * Checks whether the stream resource on windows is terminal.
     *
     * @param resource|string|int $resource The resource type to check 
     *      (e.g. `Terminal::STD_*`, `STDIN`, `STDOUT`).
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

        if(ENVIRONMENT === 'testing'){
            return true;
        }

        return @$function(self::getStd($resource));
    }

    /**
     * Check if a process is still running.
     * 
     * @param int $pid Process ID.
     * 
     * @return bool Return true if process is running
     */
    public static function isProcessRunning(int $pid): bool
    {
        if (is_platform('windows')) {
            $output = [];
            exec("tasklist /FI " . escapeshellarg("PID eq $pid") . " 2>NUL", $output);

            return count($output) > 1; 
        }

        return posix_kill($pid, 0);
    }

    /**
     * Check if command is help command.
     * 
     * @param string|array|null $command Command name to check or command array options.
     * 
     * @return bool Return true if command is help, false otherwise.
     */
    public static function isHelp(string|array|null $command = null): bool 
    {
        if(!$command){
            return self::hasArgument('--help') || self::hasArgument('-h');
        }

        $command = $command['options'] ?? $command;

        if(is_string($command)){
            return preg_match('/^(-h|--help)$/', $command) === 1;
        }

        foreach($command as $arg){
            $help = ltrim($arg, '-');

            if ($help === 'help' || $help === 'h') {
                return true;
            }
        }

        return false;
    }
    
    /**
     * Terminate a running process
     * 
     * @param int $pid Process ID.
     * @param bool $force Forcefully terminate the process.
     * 
     * @return bool Return true if termination was successful
     */
    public static function killProcess(int $pid, bool $force = false): bool
    {
        if (!self::isProcessRunning($pid)) {
            return true;
        }
        
        if (is_platform('windows')) {
            $signal = $force ? '/F' : '';
            exec("taskkill $signal /PID $pid >NUL 2>&1", $output, $returnCode);

            return $returnCode === 0;
        }
        
        $signal = $force ? SIGKILL : SIGTERM;
        return posix_kill($pid, $signal);
    }
    
    /**
     * Wait for a process to complete.
     * 
     * @param int $pid Process ID.
     * @param int $timeout Timeout in seconds (0 for no timeout).
     * 
     * @return int|null Return exit code or null if timeout/error
     */
    public static function waitForProcess(int $pid, int $timeout = 0): ?int
    {
        $start = time();

        while (self::isProcessRunning($pid)) {
            if ($timeout > 0 && (time() - $start) >= $timeout) {
                return null; 
            }

            usleep(100_000);
        }


        if (!is_platform('windows') && function_exists('pcntl_waitpid')) {
            $status = 0;
            pcntl_waitpid($pid, $status, WNOHANG);

            if (pcntl_wifexited($status)) {
                return pcntl_wexitstatus($status);
            }
        }

        // Unknown exit code (Windows or fallback)
        return 0;
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
    public static function call(string $command, array $options): bool
    {
        static $input = null;

        if(Novakit::hasCommand($command, 'system')){
            if(!$input instanceof Input){
                $input = new Input($options);
            }else{
                $input->replace($options);
            }

            return Novakit::execute($input, $options, 'system') === STATUS_SUCCESS;
        }

        return false;
    }

    /**
     * Checks whether framework has the requested command.
     *
     * @param string $command Command name to check.
     * 
     * @return bool Return true if command exist, false otherwise.
     */
    public static function hasCommand(string $command): bool
    {
        return Novakit::has($command, 'system');
    }

    /**
     * Determines if a specific command argument is present.
     *
     * Supports both short (-f) and long (--flag) forms.
     *
     * @param string $name The command argument to search for (with or without leading dashes).
     *
     * @return bool Returns true if the argument exists, false otherwise.
     */
    public static function hasArgument(string $name): bool
    {
        $normalized = ltrim($name, '-');

        foreach ($_SERVER['argv'] ?? [] as $arg) {
            if ($name === $arg || ltrim($arg, '-') === $normalized) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieves key system and environment information in a structured array format.
     *
     * This method collects details about the PHP runtime, operating system,
     * terminal environment, and framework versions to provide a summary
     * suitable for diagnostics or display in CLI tools.
     *
     * @return array<int,array{Name:string,Value:string}> Return an associative array of system information.
     */
    public static function getSystemInfo(): array 
    {
        if(self::$session['metadata'] !== null){
            return self::$session['metadata'];
        }

        return self::$session['metadata'] = [
            ['Name' => 'PHP Version', 'Value' => PHP_VERSION],
            ['Name' => 'App Version', 'Value' => APP_VERSION],
            ['Name' => 'Framework Version', 'Value' => Luminova::VERSION],
            ['Name' => 'Novakit Version', 'Value' => Luminova::NOVAKIT_VERSION],
            ['Name' => 'OS Name', 'Value' => php_uname('s')],
            ['Name' => 'OS Version', 'Value' => php_uname('v')],
            ['Name' => 'OS Model', 'Value' => self::getSystemModel()],
            ['Name' => 'Machine Type', 'Value' => php_uname('m')],
            ['Name' => 'Host Name', 'Value' => php_uname('n')],
            ['Name' => 'MAC Address', 'Value' => IP::getMacAddress()],
            ['Name' => 'Process Id','Value' =>  self::getPid()],
            ['Name' => 'Current User (PHP)', 'Value' => get_current_user()],
            ['Name' => 'Whoami', 'Value' => self::whoami() ?: 'Unavailable'],
            ['Name' => 'Terminal Name','Value' =>  self::getTerminalName()],
            ['Name' => 'Terminal Width', 'Value' => self::getHeight()],
            ['Name' => 'Terminal Height', 'Value' => self::getWidth()],
            ['Name' => 'Color Supported', 'Value' => self::isColorSupported() ? 'Yes' : 'No'],
            ['Name' => 'ANSI Supported', 'Value' => self::isAnsiSupported() ? 'Likely Yes' : 'Unknown/No'],
            ['Name' => 'Shell', 'Value' => getenv('SHELL') ?: getenv('ComSpec') ?: 'Unknown'],
            ['Name' => 'TERM Variable', 'Value' => getenv('TERM') ?: 'Unknown']
        ];
    }

    /**
     * Generates a consistent and unique system identifier for CLI authentication.
     *
     * This method constructs a persistable identifier by hashing a combination of
     * machine-specific and user-specific data, including the MAC address and key 
     * environment/system values. The result is a unique hash that varies across
     * machines, users, or terminal sessions.
     *
     * @param string $prefix Optional prefix to prepend (default: '').
     * @param string $algo Hashing algorithm to use (default: 'sha256').
     * @param bool $binary Whether to return raw binary output (default: false).
     *
     * @return string Return a hashed system identifier based on the specified algorithm.
     */
    public static function getSystemId(
        string $prefix = '', 
        string $algo = 'sha256',  
        bool $binary = false
    ): string
    {
        if(self::$session['id'] !== null){
            return self::$session['id'];
        }

        $info = [
            php_uname('n'),
            php_uname('v'),
            php_uname('m'),
            Luminova::VERSION,
            Luminova::NOVAKIT_VERSION,
            IP::getMacAddress(),
            self::getSystemModel(),
            self::getPid(),
            get_current_user() ?: self::whoami(),
            getenv('SHELL') ?: getenv('ComSpec') ?: 'Unknown',
            getenv('TERM') ?: 'Unknown'
        ];

        return self::$session['id'] = $prefix . hash($algo, implode('|', $info), $binary);
    }

    /**
     * Get the current process PID (validated).
     *
     * This always returns the PID of the running PHP process
     * and guarantees a positive integer.
     *
     * @return int Return current PID or 0 if unavailable.
     */
    public static function getPid(): int 
    {
        $pid = (int) getmypid();
        return $pid > 0 ? $pid : 0;
    }

    /**
     * Get the parent process ID (PPID) of the current process.
     *
     * Uses platform-native tools:
     * - Unix-like systems: `ps`
     * - Windows: `wmic`
     *
     * The value is cached per session.
     *
     * @return int Parent process ID, or 0 if unavailable.
     */
    public static function getParentPid(): int
    {
        if (self::$session['ppid'] !== null) {
            return self::$session['ppid'];
        }

        $ppid = 0;
        $pid  = getmypid();

        if (is_platform('windows')) {
            $output = self::_exec(
                "wmic process where (ProcessId={$pid}) get ParentProcessId /value"
            );

            if ($output && preg_match('/ParentProcessId=(\d+)/', $output, $m)) {
                $ppid = (int) $m[1];
            }
        } else {
            $output = self::_exec("ps -o ppid= -p {$pid}");

            if ($output !== null) {
                $ppid = (int) trim($output);
            }
        }

        return self::$session['ppid'] = max(0, $ppid);
    }

    /**
     * Retrieve the system's model name (e.g., MacBook Pro, Dell XPS).
     *
     * @return string Return model name or `Unknown` if not found.
     */
    public static function getSystemModel(): string
    {
        if (PHP_OS_FAMILY === 'Darwin') {
            $model = self::_exec("sysctl -n hw.model");
        }elseif (PHP_OS_FAMILY === 'Windows') {
            $output = self::_exec('wmic computersystem get model /value');
            if (preg_match('/Model=(.*)/i', $output, $matches)) {
                $model = $matches[1];
            }
        }elseif (PHP_OS_FAMILY === 'Linux') {
            $model = @file_get_contents('/sys/devices/virtual/dmi/id/product_name');

            if (!$model) {
                $model = self::_exec('dmidecode -s system-product-name');
            }
        }

        if(!$model){
            return self::$session['model'] = 'Unknown';
        }

        return self::$session['model'] = trim($model) ?: 'Unknown';
    }

    /**
     * Retrieves the name of the terminal in use.
     *
     * For Windows, it tries to detect PowerShell or Command Prompt.
     * On Unix-based systems, it returns the TTY device name.
     *
     * @return string Terminal name or 'N/A' if undetectable.
     */
    public static function getTerminalName(): string 
    {
        if (is_platform('windows')) {
            if (getenv('PSModulePath') !== false) {
                return self::$session['name'] = 'PowerShell';
            }

            $comspec = getenv('ComSpec');
            return self::$session['name'] = ($comspec ? basename($comspec) : 'N/A');
        }

        return self::$session['name'] = trim(self::_exec('tty') ?? 'N/A');
    }

    /**
     * Read input from STDIN, supporting single-line or EOF-based input.
     *
     * This is a convenience wrapper around {@see read()} for non-interactive use.
     * It is intended for piped input or bulk data, not user prompts.
     *
     * @param string $default Default value returned if input is empty or reading fails.
     * @param bool $eof Read until EOF if true, otherwise read a single line.
     * @param bool $newStream Open a new STDIN stream as a separate handle.
     *
     * @return string Returns trimmed input string or the default value.
     */
    public static function readInput(string $default = '', bool $eof = true, bool $newStream = false): string
    {
        return self::read($default, $eof, $newStream);
    }

    /**
     * Display an card-block message style in the console.
     *
     * @param string $text The message to display.
     * @param int $std The handler.
     * @param string|null $foreground Optional foreground color (default: blue).
     * @param string|null $background Optional background color (default: none).
     * @param int|null $width Optional width of the block (default: auto).
     *
     * @return void
     */
    private static function card(
        string $text, 
        int $std,
        ?string $foreground = 'blue', 
        ?string $background = null,
        ?int $width = null
    ): void
    {
        if(($foreground || $background) && !self::isColorSupported($std)){
            $foreground = $background = null;
        }

        self::fwrite(
            Text::block($text, Text::LEFT, 1, $foreground, $background, width: $width),
            $std
        );
    }

    /**
     * Add help information.
     * 
     * @param array $option Help line options.
     * @param string $key Help line key.
     * @param string $leftPadding
     * 
     * @return void
     */
    private static function printHelp(array $options, string $key, string $leftPadding): void 
    {
        if($options === []){
            return;
        }
        
        $color = self::isColorSupported() 
            ? (($key === 'usages') ? 'cyan' : (($key === 'usages') ? 'lightYellow' : 'lightGreen'))
            : null;
        self::writeln(ucfirst($key) . ':', 'lightYellow');

        $largest = 0;

        if($key === 'options'){
            foreach(array_keys($options) as $option){
                $length = strlen($option);

                if($length > $largest){
                    $largest = $length;
                }
            }
        }

        $rightPadding = Text::padding('', 9, Text::RIGHT);
        
        foreach($options as $info => $value){
            $value = trim($value);
            
            if($key === 'options'){
                $label = is_string($info) ? Color::style($info, $color) : '';
                $spacing = Text::padding('', ($largest + 6) - strlen($info), Text::RIGHT);

                self::writeln("{$leftPadding}{$label}{$spacing}{$value}");
            }else{
                if(is_string($info)){ 
                    $info  = ($color === null) ? $info : Color::style($info, $color);

                    self::writeln($leftPadding . $info);
                    self::writeln($rightPadding . $value);
                }else{
                    self::writeln($leftPadding . $value, ($key === 'usages') ? $color : null);
                }
            }
        }
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
     * Get table corners.
     * 
     * @param string $position The corner position.
     * 
     * @return string Return the table corner based on position.
     */
    private static function getTableCorner(string $position): string
    {
        return match($position){
            'topLeft' => Text::corners('topLeft'),
            'topRight' => Text::corners('topRight'),
            'bottomLeft' => Text::corners('bottomLeft'),
            'bottomRight' => Text::corners('bottomRight'),
            'topConnector' => Text::corners('topConnector'),
            'bottomConnector' => Text::corners('bottomConnector'),
            'rightConnector' => Text::corners('rightConnector'),
            'leftConnector' => Text::corners('leftConnector'),
            'horizontal' => Text::corners('horizontal'),
            'crossings' => Text::corners('crossings'),
            'vertical' => Text::corners('vertical'),
            default => ''
        };
    }

    /**
     * Calculate the maximum width for each column header based on the header name
     * and the content in each row. The width is determined by the largest visual
     * length of any value in that column.
     *
     * @param array $headers The list of column headers.
     * @param array $rows The rows of table data, where each row is an associative array.
     *
     * @return array An array of maximum widths for each header, in the same order as $headers.
     */
    private static function getTableHeaderWidths(array $headers, array $rows): array 
    {
        return array_map(
            fn($header) => max(
                Text::largest($header)[1], 
                max(array_map(fn($value) => Text::largest($value)[1], array_column($rows, $header)))
            ), 
            $headers
        );
    }

    /**
     * Calculate the maximum height (number of lines) needed for each row in the table,
     * based on wrapped content width and whether line breaks should be retained.
     *
     * @param array $headers The list of column headers.
     * @param array $rows The rows of table data.
     * @param array $widths The calculated widths for each column.
     * @param bool $isRetainNewline Whether to keep existing newlines when wrapping text.
     *
     * @return array An array of heights (line counts) for each row.
     */
    private static function getTableHeaderHeights(
        array $headers, 
        array $rows, 
        array $widths,
        bool $isRetainNewline
    ): array 
    {
        return array_map(
            fn($row) => max(array_map(fn($header) => Text::height(
                $isRetainNewline 
                    ? Text::wrap($row[$header], $widths[array_search($header, $headers)]) 
                    : $row[$header]
            ), $headers)),
            $rows
        );
    }

    /**
     * Generates a horizontal border line for the table based on column widths.
     *
     * @param array $widths Array of column widths.
     * @param string $left Character used for the left corner of the border line.
     * @param string $connector Character used to connect columns within the border line.
     * @param string $right Character used for the right corner of the border line.
     * @param ?string $color Optional color to apply to the border line.
     * 
     * @return string Return formatted string representing the horizontal border line.
     */
    private static function tBorder(
        array $widths, 
        string $left, 
        string $connector, 
        string $right, 
        ?string $color
    ): string
    {
        $border = self::getTableCorner($left);
        foreach ($widths as $i => $width) {
            $border .= str_repeat(self::getTableCorner('horizontal'), $width + 2);
            $border .= $i < count($widths) - 1 
                ? self::getTableCorner($connector)
                : self::getTableCorner($right);
        }

        return Color::style($border, $color) . PHP_EOL;
    }
    
    /**
     * Generates a table row with formatted cell values or headers.
     *
     * @param array $row Array of cell values for each column in the row.
     * @param array $widths Array of column widths, matching the indices of $row.
     * @param ?string $foreground Optional color to apply to the cell content.
     * @param bool $isHeader Flag indicating if the row is a header row; applies bold styling if true.
     * 
     * @return string Return formatted string representing the row with each cell padded to its column width.
     */
    private static function trow(
        array $row, 
        array $widths, 
        ?string $foreground, 
        string $verticalBorder,
        bool $isHeader = false
    ): string
    {
        $tCell = $verticalBorder;

        foreach ($row as $i => $cell) {
            $tCell .= ' ' . Color::apply(
                str_pad($cell, $widths[$i]), 
                $isHeader ? Text::FONT_BOLD : Text::NO_FONT, 
                $foreground
            );
            $tCell .= ' ' . $verticalBorder;
        }

        return $tCell . PHP_EOL;
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
    }

    /**
     * Generates and returns the formatted menu list with the selected option highlighted.
     *
     * @param array $options The list of options to display.
     * @param int $index The current selected index.
     * @param string $foreground The foreground color for the highlighted selection.
     * @param string|null $background The optional background color for the highlighted selection.
     * @param string|null $placeholder Optional placeholder text to display above the list (default: `null`).
     * 
     * @return string Return the formatted list output.
     */
    private static function tablistUpdate(
        array $options, 
        int $index,
        string $foreground = 'green', 
        ?string $background = null,
        ?string $placeholder = null
    ): string
    {
        self::clear();
        self::writeln(
            $placeholder ?: 'Use Arrow(↑/↓) or Tab to navigate, Enter to select:'
        );
        self::newLine();
        $list = '';

        foreach ($options as $i => $option) {
            $list .= ($i === $index) 
                ? Color::style("> {$option}", $foreground, $background) . "\n"
                : "  {$option}\n"
            ;
        }

        return $list;
    }

    /**
     * Checks if command is executing from the list of supports ANSI terminals.
     *
     * @return bool Return true if terminal is in list of ANSI supported, false otherwise.
     */
    private static function isLinuxAnsi(): bool
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
    private static function getLinuxHiddenPassword(string $message, int $timeout, bool $invisible): string
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
     * Read a single line from STDIN.
     *
     * Internal helper used by input readers to fetch one line from STDIN,
     * optionally using a separate stream handle.
     *
     * @param bool $newStream Open a new STDIN stream as a separate handle.
     *
     * @return string|null Trimmed input line, or null if reading fails or input is empty.
     */
    private static function readFromInput(bool $newStream): ?string 
    {
        $handle = $newStream ? @fopen(self::STDPATH['STDIN'], 'rb') : STDIN;

        if (!$handle) {
            return null;
        }

        $line = fgets($handle);
        if ($newStream && is_resource($handle)) {
            fclose($handle);
        }

        if ($line !== false && $line !== '') {
            return trim($line);
        }

        return null;
    }

    /**
     * Prompts the user to input a hidden password using a Windows dialog box, 
     * which utilizes a temporary VBScript file to create an input box for password entry.
     *
     * @param string $message The message to display to the user prompting for the password.
     * @param int $timeout The maximum time in seconds to wait for user input before timing out.
     *
     * @return string Return the entered password, or an empty string 
     *          if no password was provided or an error occurred.
     * @ignore
     */
    private static function getWindowsHiddenPassword(string $message, int $timeout): string
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
    private static function getVisibleWindow(): void
    {
        if (self::$windowHeight !== null && self::$windowWidth !== null) {
            return;
        }

        $height = 0;
        $width = 0;

        if (is_platform('windows')) {
            // Use PowerShell to get console size on Windows
            $size = self::_shell(
                'powershell -command "Get-Host | ForEach-Object { $_.UI.RawUI.WindowSize.Height; $_.UI.RawUI.WindowSize.Width }"'
            );

            if ($size) {
                $dimensions = explode("\n", trim($size));
                $height = isset($dimensions[0]) ? (int)$dimensions[0] : 0;
                $width  = isset($dimensions[1]) ? (int)$dimensions[1] : 0;
            }else{
                $size = self::_exec('mode con');
                preg_match('/Columns:\s+(\d+)/i', $size, $col);
                preg_match('/Lines:\s+(\d+)/i', $size, $row);

                $height = isset($row[1]) ? (int)$row[1] : 0;
                $width  = isset($col[1]) ? (int)$col[1] : 0;
            }
        } else {
            // Fallback for Unix-like systems
            $size = self::_exec('stty size');
            if ($size && preg_match('/(\d+)\s+(\d+)/', $size, $matches)) {
                $height = isset($matches[1]) ? (int)$matches[1] : 0;
                $width  = isset($matches[2]) ? (int)$matches[2] : 0;
            }
        }

        self::$windowHeight = ($height === 0) ? (int) self::_exec('tput lines') : $height;
        self::$windowWidth  = ($width === 0) ? (int) self::_exec('tput cols') : $width;
    }
}