<?php
/**
 * Luminova Framework abstract Config class for managing application configurations.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Base;

use \Luminova\Luminova;
use \Psr\Log\AbstractLogger;
use \Luminova\Interface\{HttpRequestInterface, LazyObjectInterface};

abstract class Configuration implements LazyObjectInterface
{
    /**
     * Stores the Content-Security-Policy (CSP) directives.
     * 
     * @var array $cspDirectives
     */
    private array $cspDirectives = [];

    /**
     * Application nonce value for CPS directive.
     * 
     * @var string|null $nonce 
     */
    protected static ?string $nonce = null;

    /**
     * File extensions based on MIME types.
     * Where the key is the MIME type and the value is the extension.
     * 
     * @var array<string,string> $extensions 
     * 
     * @example - Usage example:
     * 
     * ```php 
     * protected static array $extensions = [
     *      'image/jpeg' => 'jpg',
     *      'image/png' => 'png',
     * ];
     * ```
     * > **Note:** Only define this property in `App\Config\Files` class.
     */
    protected static array $extensions = [];

    /**
     * Constructor to initialize the class and trigger onCreate hook.
     */
    public function __construct()
    {
        $this->onCreate();
    }

    /**
     * Non-static property getter.
     *
     * @param string $key The property key.
     * 
     * @return mixed|null Return the property value, or null if not found.
     * 
     * @ignore 
     */
    public function __get(string $key): mixed
    {
        return property_exists($this, $key)
            ? $this->{$key}
            : null;
    }

    /**
     * Static property getter.
     *
     * @param string $key The property key.
     * 
     * @return mixed|null Return the property value, or null if not found.
     * 
     * @ignore 
     * @internal
     */
    public static function __getStatic(string $key, mixed $default = null): mixed
    {
        return Luminova::isPropertyExists(static::class, $key)
            ? static::${$key}
            : $default;
    }

    /**
     * onCreate method that gets triggered on object creation, 
     * designed to be overridden in subclasses for custom initialization.
     * 
     * @return void
     */
    protected function onCreate(): void {}

    /**
     * Retrieve environment configuration variables with optional type casting.
     *
     * @param string $key       The environment variable key to retrieve.
     * @param mixed  $default   The default value to return if the key is not found.
     * @param string|null $return    The expected return type. Can be one of:
     *                     - 'bool', 'int', 'float', 'double', 'nullable', or 'string'.
     * 
     * @return mixed  Returns the environment variable cast to the specified type, or default if not found.
     */
    public static final function getEnv(string $key, mixed $default = null, ?string $return = null): mixed 
    {
        $value = env($key, $default);

        if ($return === null || !is_string($value)) {
            return $value;
        }

        return match (strtolower($return)) {
            'bool' => (bool) $value,
            'int' => (int) $value,
            'float' => (float) $value,
            'double' => (double) $value,
            'nullable' => ($value === '') ? null : $value,
            'string' => (string) $value,
            default => $value,
        };
    }

    /**
     * Generate or retrieve a nonce with an optional prefix.
     *
     * @param int $length The length of the random bytes to generate (default: 16).
     * @param string $prefix An optional prefix for the nonce (default: '').
     * 
     * @return string Return a cached generated script nonce.
     */
    public static final function getNonce(int $length = 16, string $prefix = ''): string
    {
        return self::$nonce ??= $prefix . bin2hex(random_bytes((int) ceil($length / 2)));
    }

    /**
     * Add a directive to the Content-Security-Policy (CSP).
     *
     * @param string $directive  The CSP directive name (e.g., 'default-src').
     * @param array|string $values The values for the directive (can be a string or an array of values).
     * 
     * @return static Returns the instance of configuration class.
     */
    protected function addCsp(string $directive, array|string $values): self
    {
        $this->cspDirectives[$directive] = array_merge(
            $this->cspDirectives[$directive] ?? [], 
            (array) $values
        );

        return $this;
    }

    /**
     * Remove a directive from the Content-Security-Policy (CSP).
     *
     * @param string $directive The CSP directive to remove.
     * 
     * @return static Returns the instance of configuration class.
     */
    public function removeCsp(string $directive): self
    {
        unset($this->cspDirectives[$directive]);
        return $this;
    }

    /**
     * Clear all directives from the Content-Security-Policy (CSP).
     *
     * @return static Returns the instance of configuration class.
     */
    public function clearCsp(): self
    {
        $this->cspDirectives = [];
        return $this;
    }

    /**
     * Build and return the Content-Security-Policy (CSP) as a string.
     *
     * @return string Returns the CSP policy string in the correct format.
     */
    public function getCsp(): string
    {
        static $csp = null;

        if($csp !== null){
            return $csp;
        }

        $policies = [];

        foreach ($this->cspDirectives as $directive => $values) {
            $policies[] = $directive . ' ' . implode(' ', array_unique($values));
        }

        return $csp = implode('; ', $policies);
    }

    /**
     * Generate the `<meta>` tag for embedding the CSP in HTML documents.
     *
     * @param string $id The CSP element identifier (default: none).
     * 
     * @return string Returns the `<meta>` tag with the CSP as the content.
     */
    public function getCspMetaTag(string $id = ''): string
    {
        $id = ($id !== '') ? 'id="' . $id . '" ' : '';
        return '<meta http-equiv="Content-Security-Policy" '. $id .'content="' . htmlspecialchars($this->getCsp(), ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Send the Content-Security-Policy (CSP) as an HTTP header.
     * 
     * @return void
     */
    public function sendCspHeader(): void
    {
        header('Content-Security-Policy: ' . $this->getCsp());
    }

    /**
     * Get the file extension based on the MIME type.
     *
     * @param string $mimeType The MIME type of the file.
     *
     * @return string Return the corresponding file extension (without the dot),
     *                or 'bin' if the MIME type is not recognized.
     */
    public static function getExtension(string $mimeType): string 
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
            'image/svg+xml' => 'svg',
            'image/tiff' => 'tiff',
            'image/avif' => 'avif',
            'image/x-icon' => 'ico',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            'text/markdown' => 'md',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/zip' => 'zip',
            'application/gzip' => 'gzip',
            'application/x-tar' => 'tar',
            'application/x-gzip' => 'gz',
            'application/x-bzip2' => 'bz2',
            'application/x-7z-compressed' => '7z',
            'application/x-rar-compressed' => 'rar',
            'application/x-msdownload' => 'exe',
            'application/x-dosexec' => 'dos',
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/ogg', 'video/ogg', 'application/ogg' => 'ogg',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/x-msvideo' => 'avi',
            'video/x-matroska' => 'mkv',
            'video/quicktime' => 'mov',
            'text/html' => 'html',
            'text/css' => 'css',
            'application/json' => 'json',
            'application/xml' => 'xml',
            'text/csv' => 'csv',
            'application/javascript' => 'js',
            'application/rtf' => 'rtf',
            'application/x-sh' => 'sh',
            'application/x-php' => 'php',
            'application/octet-stream' => 'bin',
            default => ltrim(self::$extensions[$mimeType] ?? '', '.'),
        };
    }

    /**
     * Customize and generate an HTML email template for logging system notifications.
     *
     * @param HttpRequestInterface $request The HTTP request object containing information about the request.
     * @param AbstractLogger|\Luminova\Logger\NovaLogger $logger The instance of logger class.
     * @param string $message The log message.
     * @param string $level The log level (e.g., 'info', 'warning', 'error').
     * @param array<string|int,mixed> $context Additional context information for the log message.
     *
     * @return string|null Return the HTML email template or null to use default.
     */
    public static function getEmailLogTemplate(
        HttpRequestInterface $request,
        AbstractLogger $logger,
        string $message,
        string $level,
        array $context
    ): ?string
    {
        return null;
    }
}