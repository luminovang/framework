<?php
/**
 * Luminova Framework abstract BaseConfig class for managing application configurations.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Base;

abstract class BaseConfig
{
    /**
     * Stores the Content-Security-Policy (CSP) directives.
     * 
     * @var array $cspDirectives
     */
    private array $cspDirectives = [];

    /**
     * @var string|null $nonce 
     */
    protected static ?string $nonce = null;

    /**
     * Constructor to initialize the class and trigger onCreate hook.
     */
    public function __construct()
    {
        $this->onCreate();
    }

    /**
     * onCreate method that gets triggered on object creation, 
     * designed to be overridden in subclasses for custom initialization.
     * 
     * @return void
     */
    protected function onCreate(): void 
    {}

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
        return self::$nonce ??= $prefix . bin2hex(random_bytes($length / 2));
    }

    /**
     * Add a directive to the Content-Security-Policy (CSP).
     *
     * @param string $directive  The CSP directive name (e.g., 'default-src').
     * @param array|string $values The values for the directive (can be a string or an array of values).
     * 
     * @return self Returns the instance of configuration class.
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
     * @return self Returns the instance of configuration class.
     */
    public function removeCsp(string $directive): self
    {
        unset($this->cspDirectives[$directive]);
        return $this;
    }

    /**
     * Clear all directives from the Content-Security-Policy (CSP).
     *
     * @return self Returns the instance of configuration class.
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
    public function getCspHeader(): void
    {
        header('Content-Security-Policy: ' . $this->getCsp());
    }
}