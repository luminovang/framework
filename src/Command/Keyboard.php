<?php 
/**
 * Luminova Framework CLI Keyboard event manager.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Command;

use \Luminova\Exceptions\RuntimeException;

final class Keyboard
{
    // Arrow keys
    public const UP = 'up';
    public const DOWN = 'down';
    public const LEFT = 'left';
    public const RIGHT = 'right';

    // Navigation
    public const HOME = 'home';
    public const END = 'end';
    public const INSERT = 'insert';
    public const DELETE = 'delete';
    public const PAGE_UP = 'page_up';
    public const PAGE_DOWN = 'page_down';

    // Function keys
    public const F1  = 'f1';
    public const F2  = 'f2';
    public const F3  = 'f3';
    public const F4  = 'f4';
    public const F5  = 'f5';
    public const F6  = 'f6';
    public const F7  = 'f7';
    public const F8  = 'f8';
    public const F9  = 'f9';
    public const F10 = 'f10';
    public const F11 = 'f11';
    public const F12 = 'f12';

    // Editing
    public const BACKSPACE  = 'backspace';
    public const TAB        = 'tab';
    public const SHIFT_TAB  = 'shift+tab';

    // Control
    public const ENTER   = 'enter';
    public const ESCAPE  = 'escape';
    public const CTRL_A  = 'ctrl+a';
    public const CTRL_C  = 'ctrl+c';
    public const CTRL_V  = 'ctrl+v';

    // Heuristic
    public const CAPSLOCK = 'capslock';

    /**
     * ANSI key sequence map.
     *
     * @var array<string,string> KEY_MAP
     */
    private const KEY_MAP = [
        "\033[A" => self::UP,
        "\033[B" => self::DOWN,
        "\033[C" => self::RIGHT,
        "\033[D" => self::LEFT,
        "\033[H"  => self::HOME,
        "\033[F"  => self::END,
        "\033[1~" => self::HOME,
        "\033[4~" => self::END,
        "\033[2~" => self::INSERT,
        "\033[3~" => self::DELETE,
        "\033[5~" => self::PAGE_UP,
        "\033[6~" => self::PAGE_DOWN,
        "\033OP"   => self::F1,
        "\033OQ"   => self::F2,
        "\033OR"   => self::F3,
        "\033OS"   => self::F4,
        "\033[15~" => self::F5,
        "\033[17~" => self::F6,
        "\033[18~" => self::F7,
        "\033[19~" => self::F8,
        "\033[20~" => self::F9,
        "\033[21~" => self::F10,
        "\033[23~" => self::F11,
        "\033[24~" => self::F12,
        "\x7F" => self::BACKSPACE,
        "\x08" => self::BACKSPACE,
        "\t"   => self::TAB,
        "\033[Z" => self::SHIFT_TAB,
        "\n"   => self::ENTER,
        "\r"   => self::ENTER,
        "\x01" => self::CTRL_A,
        "\x16" => self::CTRL_V,
        "\x03" => self::CTRL_C,
        "\033" => self::ESCAPE,
    ];

    /**
     * Return all key names currently supported by the Keyboard class.
     *
     * This includes all mapped keys from KEY_MAP plus heuristic keys like CAPSLOCK.
     * Also includes dynamic keys `ctrl+{letter}, ctrl+alt+{letter}, alt+{letter}`.
     *
     * @return string[] Returns list of all supported keyboard key names.
     */
    public static function keys(): array
    {
        static $keys = null;

        if ($keys === null) {
            $keys = array_unique(array_merge(
                array_values(self::KEY_MAP), 
                [self::CAPSLOCK, 'ctrl+', 'ctrl+alt+', 'alt+']
            ));
        }

        return $keys;
    }

    /**
     * Check whether a given key name is supported by the Keyboard class.
     * 
     * Also support dynamic keys `ctrl+{letter}, ctrl+alt+{letter}, alt+{letter}`
     *
     * @param string $name The name of the key to check (e.g, `Keyboard::ESCAPE`).
     * 
     * @return bool Returns true if the key is supported, false otherwise.
     */
    public static function isSupported(string $name): bool
    {
        return in_array($name, self::keys(), true)
            || str_starts_with($name, 'ctrl+')
            || str_starts_with($name, 'ctrl+alt+')
            || str_starts_with($name, 'alt+');
    }

    /**
     * Capture a single key press from the terminal.
     *
     * Reads raw keyboard input and maps common control keys to human-readable names.
     * Designed for interactive CLI usage (menus, navigation, confirmations). 
     * 
     * If a timeout is provided, waits up to $timeout milliseconds for a key.
     * If no key is pressed during that time, returns null.
     *
     * @param int|null $timeout Optional wait timeout in milliseconds (default: null).
     * @param (callable(string $code, ?string $name):mixed)|null $callback
     *        Optional callback invoked with the raw ANSI key sequence and its mapped name.
     *
     * @return array{code:string,name:?string}|mixed Returns key event or the callback result.
     * > **Note:**
     * > Terminals do not emit a real Caps Lock key event.
     * > Caps Lock is detected when an uppercase letter is received.
     */
    public static function capture(?int $timeout = null, ?callable $callback = null): mixed
    {
        if ($timeout !== null) {
            $event = self::detectWithTimeout($timeout, $callback);
            return $event;
        }

        return self::detect($callback);
    }

    /**
     * Listen for key events and fire a callback when a matching key is detected.
     *
     * Continuously reads keyboard input and triggers the given callback for each key event
     * that matches the provided filter. The listener can optionally stop after a timeout
     * or when the callback returns `true`.
     *
     * @param callable(string $code, ?string $name):bool $callback Callback invoked for each matching key press.
     *        Must return a boolean:
     *          - `true` to stop listening,
     *          - `false` to continue listening.
     * @param string[] $listeners List of key names to listen for. Only these keys trigger the callback.
     * @param int|null $timeout Optional timeout in milliseconds (default: `null` wait indefinitely).
     *
     * @return void
     * @throws RuntimeException If the callback returns a non-boolean value.
     *
     * @example - Listen for arrow keys and stop on ESC:
     * ```php
     * use Luminova\Command\Terminal;
     * 
     * Keyboard::listen(
     *     function (string $code, ?string $name): bool {
     *         Terminal::writeln("Pressed: {$name}");
     *         return $name === Keyboard::ESCAPE; // stop on ESC
     *     },
     *     [
     *         Keyboard::UP,
     *         Keyboard::DOWN,
     *         Keyboard::LEFT,
     *         Keyboard::RIGHT,
     *         Keyboard::ESCAPE
     *     ],
     *     10000 // optional 10 second timeout
     * );
     * ```
     */
    public static function listen(callable $callback, array $listeners, ?int $timeout = null): void
    {
        $isTimeout = ($timeout !== null);
        $start = $isTimeout ? microtime(true) : 0;

        while (true) {
            if ($isTimeout) {
                $elapsed = (microtime(true) - $start) * 1000;
                if ($elapsed >= $timeout) {
                    break;
                }
            }

            $event = $isTimeout
                ? self::detectWithTimeout(max(0, $timeout - (int)((microtime(true) - $start) * 1000)))
                : self::detect();

            if ($event === null || !in_array($event['name'], $listeners, true)) {
                continue;
            }

            $stop = $callback($event['code'], $event['name']);

            self::assert($stop, __METHOD__);

            if ($stop === true) {
                break;
            }
        }
    }

    /**
     * Read keys repeatedly until a specific condition is satisfied.
     *
     * Continuously reads individual key presses from the keyboard and evaluates
     * each one against the provided condition callback. When the condition returns
     * true, reading stops and the corresponding key event is returned (or passed
     * to an optional result callback).
     *
     * @param callable(string $code, ?string $name):bool $condition
     *        Callback that receives the raw key code and mapped name. 
     *        Return true to stop reading.
     * @param callable(string $code, ?string $name):mixed|null $callback
     *        Optional callback invoked with the key event when the condition is met.
     *
     * @return array{code:string,name:?string}|mixed
     *         Returns the key event or the result of the callback when the condition is satisfied.
     * @throws RuntimeException If `$condition` returns a non-boolean value.
     *
     * > **Note**
     * > The `$condition` callback must return a boolean. 
     * > Return true to stop reading or false to keep reading.
     * 
     * @example - Wait for Enter
     * ```php
     * Keyboard::readUntil(fn($k, $n) => $n === Keyboard::ENTER);
     * ```
     *
     * @example - Stop on Ctrl+C and handle
     * ```php
     * Keyboard::readUntil(
     *     fn($k, $n): bool => $n === Keyboard::CTRL_C,
     *     fn($k, $n) => exit("Interrupted\n")
     * );
     * ```
     */
    public static function readUntil(callable $condition, ?callable $callback = null): mixed
    {
        while (true) {
            $event = self::detect();
            $result = $condition($event['code'], $event['name']);

            self::assert($result, __METHOD__);

            if ($result) {
                return $callback
                    ? $callback($event['code'], $event['name'])
                    : $event;
            }
        }
    }

    /**
     * Assert that callback returns boolean value.
     *
     * @param mixed $result The callback result.
     * @return void
     * @throws RuntimeException If `$result` returns other than boolean type.
     * ```
     */
    private static function assert(mixed $result, string $fn): void
    {
        if (!is_bool($result)) {
            throw new RuntimeException(sprintf(
                '%s callback must return bool, %s returned',
                $fn,
                get_debug_type($result)
            ));
        }
    }

    /**
     * Detect a key with timeout.
     * 
     * @param int $timeout Timeout in milliseconds.
     * @param callable(string $code, ?string $name):mixed|null $callback Optional callback.
     *
     * @return array{code:string,name:?string}|mixed|null 
     *      Returns the key event if any key was pressed, or null if the timeout elapsed.
     */
    private static function detectWithTimeout(int $timeout, ?callable $callback = null): mixed
    {
        @system('stty -echo raw');

        try {
            $sec  = intdiv($timeout, 1000);
            $usec = ($timeout % 1000) * 1000;

            $read = [STDIN];
            $write = null;
            $except = null;

            if (stream_select($read, $write, $except, $sec, $usec) === 0) {
                return null;
            }

            $event = self::detect($callback);

            stream_set_blocking(STDIN, false);
            while (fread(STDIN, 1)) {}
            stream_set_blocking(STDIN, true);

            return $event;
        } finally {
            @system('stty echo cooked');
        }
    }

    /**
     * Detect a single key press or return null after a timeout.
     *
     * @param callable(string $code, ?string $name):mixed|null $callback
     *
     * @return array{code:string,name:?string}|mixed 
     *          Returns the key event or callback result if a key was pressed.
     */
    private static function detect(?callable $callback = null): mixed
    {
        @system('stty -echo raw');

        try {
            $code = fread(STDIN, 6) ?: '';
        } finally {
            @system('stty echo cooked');
        }

        $name = null;
        $len = strlen($code);

        if ($len === 2 && $code[0] === "\033") {
            $ord = ord($code[1]);

            if ($ord >= 1 && $ord <= 26) {
                $name = 'ctrl+alt+' . chr($ord + 96);
            } else {
                $name = 'alt+' . $code[1];
            }
        }
        elseif (isset(self::KEY_MAP[$code])) {
            $name = self::KEY_MAP[$code];
        }
        elseif ($len === 1) {
            $ord = ord($code);
            if ($ord >= 1 && $ord <= 26) {
                $name = 'ctrl+' . chr($ord + 96);
            } elseif (ctype_alpha($code) && ctype_upper($code)) {
                $name = self::CAPSLOCK;
            }
        }

        return $callback
            ? $callback($code, $name)
            : ['code' => $code, 'name' => $name];
    }
}