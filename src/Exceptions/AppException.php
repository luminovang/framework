<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Exceptions;

use \Exception;
use \Throwable;

class AppException extends Exception
{
    /**
     * Constructor for AppException.
     *
     * @param string message   The exception message (default: 'Database error').
     * @param int $code  The exception code (default: 500).
     * @param Throwable $previous  The previous exception if applicable (default: null).
    */
    public function __construct(string $message, int $code = 0, Throwable $previous = null)
    {
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        $message .= ' Time: ' . date('Y-m-d H:i:s');
        $message .= isset($caller['file']) ? ' file: ' .  filter_paths($caller['file']) : '';
        $message .= isset($caller['line']) ? ' on line: ' . $caller['line'] : '';

        parent::__construct($message, $code, $previous);
    }

    /**
     * Get a string representation of the exception.
     *
     * @return string A formatted error message.
    */
    public function __toString(): string
    {
        return "Error {$this->code}: {$this->message}";
    }

    /**
     * Handle the exception based on the production environment.
     * 
     * @throws $this Exception
    */
    public function handle(): void
    {
        if (PRODUCTION) {
            $this->logMessage();
        } else {
            throw $this;
        }
    }

    /**
     * Logs an exception
     *
     * 
     * @return void
    */
    public function logMessage(): void
    {
        $message = "Exception: {$this->getMessage()}";

        logger('exception', $message);
    }

    /**
     * Create and handle a Exception.
     *
     * @param string $message he exception message.
     * @param int $code The exception code (default: 500).
     * @param Throwable $previous  The previous exception if applicable (default: null).
     * 
     * @return void 
     * @throws $this Exception
    */
    public static function throwException(string $message, int $code = 0, Throwable $previous = null): void
    {
        $throw = new self($message, $code, $previous);

        $throw->handle();
    }
    
    /**
     * Creates a syntax-highlighted version of a PHP file.
     * @param string $file 
     * @param int $lineNumber 
     * @param int $lines
     * 
     * @return bool|string
    */
    public static function highlightFile(string $file, int $lineNumber, int $lines = 15): bool|string
    {
        if ($file === '' || ! is_readable($file)) {
            return false;
        }

        // Set our highlight colors:
        self::highlightColor();
        $source = @file_get_contents($file);

        if($source === false){
            return false;
        }
          
        $source = str_replace(["\r\n", "\r"], "\n", $source);
        $source = explode("\n", highlight_string($source, true));

        if (PHP_VERSION_ID < 80300) {
            $source = str_replace('<br />', "\n", $source[1]);
            $source = explode("\n", str_replace("\r\n", "\n", $source));
        } else {
            $source = str_replace(['<pre><code>', '</code></pre>'], '', $source);
        }

        // Get just the part to show
        $start = max($lineNumber - (int) round($lines / 2), 0);
        $source = array_splice($source, $start, $lines, true);
        $format = '% ' . strlen((string) ($start + $lines)) . 'd';

        $out = '';
        $spans = 0;

        foreach ($source as $n => $row) {
            $spans += substr_count($row, '<span') - substr_count($row, '</span');
            $row = str_replace(["\r", "\n"], ['', ''], $row);

            if (($n + $start + 1) === $lineNumber) {
                preg_match_all('#<[^>]+>#', $row, $tags);

                $out .= sprintf(
                    "<span class='line highlight'><span class='number'>{$format}</span> %s\n</span>%s",
                    $n + $start + 1,
                    strip_tags($row),
                    implode('', $tags[0])
                );
            } else {
                $out .= sprintf('<span class="line"><span class="number">' . $format . '</span> %s', $n + $start + 1, $row) . "\n";
                $spans++;
            }
        }

        if ($spans > 0) {
            $out .= str_repeat('</span>', $spans);
        }

        return '<pre><code>' . $out . '</code></pre>';
    }

    /**
     * Initialize ini_set highlight
    */
    private static function highlightColor(): void
    {
        if (function_exists('ini_set')) {
            ini_set('highlight.comment', '#767a7e; font-style: italic');
            ini_set('highlight.default', '#c7c7c7');
            ini_set('highlight.html', '#06B');
            ini_set('highlight.keyword', '#f1ce61;');
            ini_set('highlight.string', '#869d6a');
        }
    }
}