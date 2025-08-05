<?php
/**
 * Luminova Framework error guard message class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Foundation\Error;

use \Throwable;
use \Stringable;
use \Luminova\Luminova;
use \Luminova\Command\Terminal;
use \Luminova\Exceptions\ErrorCode;
use \Luminova\Foundation\Error\Guard;

class Message implements Stringable
{
    /**
     * Constructor for the error message class.
     * 
     * @param string $message The error message.
     * @param string|int $code The exception error code.
     * @param int $severity The error type/code (e.g., `E_*`).
     * @param string $file The file where the error occurred.
     * @param int|null $line The line number where the error occurred.
     * @param Throwable|null $previous Optional previous exception.
     * @param string $name A custom name for the error.
     * 
     * > **Note:** This class is not throwable.
     */
    public function __construct(
        protected string $message, 
        protected string|int $code = 0, 
        protected int $severity = 1,
        protected string $file = '',
        protected int $line = 0,
        private ?Throwable $previous = null,
        protected ?string $name = null
    ) {}

    /**
     * Get the error severity.
     * 
     * @return int Return the error severity.
     */
    public function getSeverity(): int 
    {
        return $this->severity;
    }

    /**
     * Get the error code.
     * 
     * @return string|int Return the error code.
     */
    public function getCode(): string|int
    {
        return Guard::getCode($this->code);
    }

    /**
     * Get the line number where the error occurred.
     * 
     * @return int Return the line number.
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * Get the file where the error occurred.
     * 
     * @return string Return the file path.
     */
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * Get the error display name.
     * 
     * @return string Return the error display name.
     */
    public function getName(): string
    {
        return $this->name ?? ErrorCode::getName($this->getCode());
    }

    /**
     * Get the error message.
     * 
     * @return string Return the error message.
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Get the filtered error message without file details.
     * 
     * @return string Return the filtered error message.
     */
    public function getDescription(): string
    {
        return Guard::sanitizeMessage($this->message);
    }

    /**
     * Get the previous error.
     * 
     * @return Throwable|null Return the previous error or null if none.
     * @ignore
     */
    public function getPrevious(): ?Throwable 
    {
        return $this->previous;
    }

    /**
     * Get the last debug backtrace from the shared error context.
     * 
     * This method accesses a shared memory $trace to retrieve
     * the stored debug backtrace. If the backtrace is not set, it returns an empty array.
     * 
     * @return array Return the debug backtrace or an empty array if not available.
     */
    public static function getBacktrace(): array 
    {
        return Guard::getBacktrace();
    }

     /**
     * Gets the stack trace.
     * 
     * @return array Returns the stack trace as an array in the same format as.
     */
    public function getTrace(): array 
    {
        return $this->getBacktrace() ?: debug_backtrace();
    }

    /**
     * Returns the raw error message when the object is printed or cast to string.
     * 
     * Triggered automatically by `echo`, `print`, or string casting.
     * 
     * @return string Return the raw error message.
     */
    public function __toString(): string
    {
        return $this->message;
    }

    /**
     * Returns a formatted error message with code, file, and line details.
     * 
     * Format: `Error: (code) message in file/path/foo.php on line N`.
     * 
     * @return string Return the formatted error message with code, file, and line number.
     */
    public function toString(): string
    {
        return sprintf(
            'Error: (%s) %s in %s on line %d',
            (string) $this->getCode(),
            $this->message,
            $this->file,
            $this->line
        );
    }

    /**
     * Converts special placeholders (<link> and <highlight>) into styled output.
     *
     * - In web contexts:
     *   - <link>URL</link> → <a href="URL"...>URL</a>
     *   - <highlight color="red">Text</highlight> → <span style="color:red">Text</span>
     *
     * - In CLI contexts:
     *   - <link>URL</link> → underlined or clickable URL (ANSI if supported)
     *   - <highlight>Text</highlight> → highlighted text using ANSI color (default yellow)
     *
     * Only valid http(s) and mailto links are converted for <link>.
     * Highlight colors are ignored in CLI mode and replaced with a default color.
     *
     * @param string $message The message containing <link> or <highlight> placeholders.
     * @param bool   $newTab  Whether to open links in a new browser tab (default: true).
     *
     * @return string The formatted text with placeholders replaced by styled links or highlights.
     *
     * @example
     * ```php
     * echo Message::prettify('See <link>https://luminova.ng/</link> and <highlight color="red">important</highlight>');
     * 
     * // Outputs (web): See <a href="https://luminova.ng/" target="_blank" rel="noopener">https://luminova.ng/</a> and <span style="color:red">important</span>
     * ```
     */
    public static function prettify(string $message, bool $newTab = true): string
    {
        $pattern = '~<(link)>(https?://[^<\s]+|mailto:[^<\s]+)</\1>|<highlight(?:\s+color="([^"]*)")?>(.*?)</highlight>~is';
        $cliColorMode = 0;

        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg' || Luminova::isCommand()) {
            Terminal::init();
            $cliColorMode = Terminal::isAnsiSupported() ? 1 : 2;
        }

        return preg_replace_callback($pattern, function ($matches) use ($newTab, $cliColorMode) {
            // If <link> matched
            if (!empty($matches[1]) && !empty($matches[2])) {
                $url = $matches[2];

                if (!filter_var($url, FILTER_VALIDATE_URL) && str_starts_with($url, 'mailto:')) {
                    return htmlspecialchars($matches[0], ENT_QUOTES);
                }

                $href = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

                if ($cliColorMode > 0) {
                    if ($cliColorMode === 2) {
                        return "\033[4m{$href}\033[0m";
                    }
                    return "\033]8;;{$href}\033\\{$href}\033]8;;\033\\";
                }

                $target = $newTab ? ' target="_blank"' : '';
                return "<a class=\"error-link\" href=\"{$href}\"{$target} rel=\"noopener\">{$href}</a>";
            }

            // If <highlight> matched
            $text = htmlspecialchars($matches[4] ?? '', ENT_QUOTES, 'UTF-8');

            if ($cliColorMode > 0) {
                if ($cliColorMode === 2) {
                    return "\033[33m{$text}\033[0m";
                }

                return $text;
            }

            $color = !empty($matches[3]) ? htmlspecialchars($matches[3], ENT_QUOTES, 'UTF-8') : 'yellow';
            $style = $color ? " style=\"color:{$color}\"" : '';

            return "<span class=\"error-highlight\"{$style}>{$text}</span>";
        }, $message);
    }
}