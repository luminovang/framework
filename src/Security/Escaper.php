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
namespace Luminova\Security;

use \Laminas\Escaper\Escaper as LaminasEscaper;
use \Luminova\Exceptions\{RuntimeException, BadMethodCallException, InvalidArgumentException};
use function \Luminova\Funcs\is_utf8;

/**
 * @mixin \Laminas\Escaper\Escaper
 */
class Escaper
{
    /**
     * Laminas Escaper object.
     * 
     * @var LaminasEscaper $escaper
     */
    protected ?LaminasEscaper $escaper = null;

    /**
     * Static escaper instance.
     * 
     * @var self $instance
     */
    private static ?self $instance = null;

    /**
     * Determine whether Escaper class is available.
     * 
     * @var bool $isEscaper
     */
    private static ?bool $isEscaper = null;

    /**
     * Escaper encoding. 
     * 
     * @var string $encoding
     */
    protected string $encoding = 'utf-8';

    /**
     * Escaper encoding flag. 
     * 
     * @var int $encodingFlags
     */
    protected int $encodingFlags = 0;

    /**
     * Escaper supported encodings. 
     * 
     * @var string[] $supportedEncodings
     */
    protected array $supportedEncodings = [
        'iso-8859-1',
        'iso8859-1',
        'iso-8859-5',
        'iso8859-5',
        'iso-8859-15',
        'iso8859-15',
        'utf-8',
        'cp866',
        'ibm866',
        '866',
        'cp1251',
        'windows-1251',
        'win-1251',
        '1251',
        'cp1252',
        'windows-1252',
        '1252',
        'koi8-r',
        'koi8-ru',
        'koi8r',
        'big5',
        '950',
        'gb2312',
        '936',
        'big5-hkscs',
        'shift_jis',
        'sjis',
        'sjis-win',
        'cp932',
        '932',
        'euc-jp',
        'eucjp',
        'eucjp-win',
        'macroman',
    ];

    /**
     * Input escaper constructor.
     * 
     * @param string|null $encoding The character encoding to use (default: 'utf-8').
     * 
     * @throws InvalidArgumentException Throws if unsupported encoding or empty string is provided.
     */
    public function __construct(?string $encoding = 'utf-8')
    {
        $this->setEncoding($encoding ?? 'utf-8');
    }

    /**
     * Create a static shared escaper instance with the given encoding.
     *
     * This method checks if the optional third-party escaper class is available.
     * If it exists, the created instance will use it internally for escaping.
     * Otherwise, the instance falls back to the built-in escaper logic.
     *
     * @param string|null $encoding The character encoding use (default: 'UTF-8').
     *
     * @return static Returns shared static instance of escaper.
     * @throws InvalidArgumentException Throws if unsupported encoding or empty string is provided.
     *
     * @example - Example:
     * 
     * ```php
     * $escaper = Escaper::with('UTF-8');
     * $escaped = $escaper->escape('<b>Hello</b>');
     * ```
     */
    public static function with(?string $encoding = null): self
    {
        if(!self::$instance instanceof self){
            self::$isEscaper ??= class_exists(LaminasEscaper::class);
            self::$instance = new static($encoding);

            if (self::$isEscaper) {
                self::$instance->escaper = new LaminasEscaper($encoding ?? 'utf-8');
            }
        }

        return self::$instance;
    }

    /**
     * Magic method to handle method calls dynamically.
     * 
     * @param string $name The name of the method being called.
     * @param array $arguments The arguments passed to the method.
     * 
     * @return mixed The result of the method call.
     * @throws BadMethodCallException When the called method does not exist.
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (!self::$isEscaper || !($this->escaper instanceof LaminasEscaper)) {
            if (method_exists($this, $name)) {
                return $this->{$name}(...$arguments);
            }

            throw new BadMethodCallException(
                sprintf('Method %s does not exist, to use it, you need to install a third-party escaper library by running "composer require laminas/laminas-escaper"', $name)
            );
        }

        if (!method_exists($this->escaper, $name)) {
            throw new BadMethodCallException(
                sprintf('Method %s does not exist in class: %s.', $name, LaminasEscaper::class)
            );
        }

        return $this->escaper->{$name}(...$arguments);
    }

    /**
     * Get the character encoding used by the escaper.
     * 
     * @return string Return the character encoding.
     */
    public function getEncoding(): string
    {
        return $this->encoding;
    }

    /**
     * Set escaper encoding type.
     * 
     * If set encoding is called when using `Laminas Escaper` library, new instance of Laminas Escaper will be created.
     * 
     * @param string $encoding The character encoding to use (e.g: 'utf-8').
     * 
     * @return static Return instance of escape class.
     * @throws InvalidArgumentException Throws if unsupported encoding or empty string is provided.
     */
    public function setEncoding(string $encoding): self
    {
        $encoding = strtolower($encoding);

        if($encoding === ''){
            throw new InvalidArgumentException(
                'Invalid encoding, expected non empty string for encoding.'
            );
        }

        if (!in_array($encoding, $this->supportedEncodings)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported encoding %s specified. Supported encodings are: %s',
                $encoding,
                implode(', ', $this->supportedEncodings)
            ));
        }

        $this->encoding = $encoding;
        $this->encodingFlags = ENT_QUOTES|ENT_SUBSTITUTE;

        if (self::$isEscaper && ($this->escaper instanceof LaminasEscaper)) {
            if($this->escaper->getEncoding() !== $encoding) {
                $this->escaper = new LaminasEscaper($this->encoding);
            }
        }

        return $this;
    }

    /**
     * Escape a string using custom escape rules.
     *
     * @param string $input The string to escape.
     * @param array $rules Associative array of custom escape rules where keys are regex patterns and values are replacement strings.
     * 
     * @return string Return the escaped string.
     */
    public static function escapeWith(string $input, array $rules): string
    {
        if($rules === '' || $rules === []){
            return $input;
        }

        foreach ($rules as $pattern => $replacement) {
            $input = preg_replace($pattern, $replacement, $input);
        }

        return $input;
    }

    /**
     * Escape HTML special characters.
     * 
     * @param string $string The string to be escaped.
     * 
     * @return string Return the escaped string.
     */
    protected function escapeHtml(string $string): string
    {
        return htmlspecialchars($string, $this->encodingFlags, $this->encoding);
    }

    /**
     * Escape HTML attribute values.
     * 
     * @param string $string The string to be escaped.
     * 
     * @return string Return the escaped string.
     */
    protected function escapeHtmlAttr(string $string): string
    {
        return htmlspecialchars($string, $this->encodingFlags, $this->encoding);
    }

    /**
     * Escape JavaScript special characters.
     * 
     * @param array|string $string The string or array of strings to be escaped.
     * 
     * @return string The escaped string or array of strings.
     */
    protected function escapeJs(array|string $string): string
    {
        return str_replace(
            ['<', '>', '\'', '"', '&', '\\'],
            ['\x3c', '\x3e', '\x27', '\x22', '\x26', '\x5c'],
            $string
        );
    }

    /**
     * Escape CSS special characters.
     * 
     * @param string $string The string to be escaped.
     * 
     * @return string Return the escaped string.
     */
    protected function escapeCss(string $string): string
    {
        return preg_replace('/[^\w\s]/i', '\\\$0', $string);
    }

    /**
     * Escape a string for the URI or Parameter contexts. 
     * This should not be used to escape an entire URI - only a sub-component being inserted. 
     * 
     * @param string $string The URL to be escaped.
     *
     * @return string Return the escaped URL.
     */
    public function escapeUrl(string $string): string
    {
        return rawurlencode($string);
    }

    /**
     * Convert a string to UTF-8 encoding.
     * 
     * @param string $string The string to be converted.
     * 
     * @return string Return the converted string.
     * @throws RuntimeException When the string is not valid UTF-8 or cannot be converted.
     */
    protected function toUtf8(string $string): string
    {

        $result = $this->encoding === 'utf-8' ? $string : $this->convertEncoding($string, 'UTF-8', $this->encoding);

        if (!is_utf8($result)) {
            throw new RuntimeException(
                sprintf('String to be escaped was not valid UTF-8 or could not be converted: %s', $result)
            );
        }

        return $result;
    }

    /**
     * Convert a string from UTF-8 encoding.
     * 
     * @param string $string The string to be converted.
     * @return string Return the converted string.
     */
    protected function fromUtf8(string $string): string
    {
        return ($this->encoding === 'utf-8') 
            ? $string 
            : $this->convertEncoding($string, $this->encoding, 'UTF-8');
    }

    /**
     * Convert a string to a different character encoding.
     * 
     * @param array|string $string The string or array of strings to be converted.
     * @param string $to The target character encoding.
     * @param array|string|null $from The source character encoding. Defaults to null (auto-detection).
     * 
     * @return string Return the converted string.
     */
    protected function convertEncoding(array|string $string, string $to, array|string|null $from = null): string
    {
        $result = mb_convert_encoding($string, $to, $from);
        return ($result === false) ? '' : $result;
    }
}