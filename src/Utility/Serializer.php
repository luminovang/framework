<?php
/**
 * Luminova Framework Closure serializer.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Utility;

use \Closure;
use \PhpToken;
use \Throwable;
use \ParseError;
use Luminova\Luminova;
use \ReflectionFunction;
use Luminova\Debugger\Tracer;
use Luminova\Exceptions\RuntimeException;

final class Serializer
{
    /**
     * @var string VERSION
     */
    public const VERSION = '1.0.0';

    /**
     * @var string SIGN_ALGO
     */
    private const SIGN_ALGO = 'sha256';

    /**
     * Return types pattern
     * 
     * @var string TYPE_PATTERN
     */
    private const TYPE_PATTERN = '[\\\\\w|?&\s]+';

    /**
     * Closure function expression.
     *
     * Supports optional static closures.
     *
     * @var string CLOSURE_FN
     */
    private const CLOSURE_FN = '(?:static\s+)?function\s*\([^)]*\)\s*(?:use\s*\([^)]*\))?\s*(?::\s*' . 
        self::TYPE_PATTERN . 
    ')?\s*\{';

    /**
     * Arrow function expression.
     *
     * Supports optional static arrow functions and optional return types.
     *
     * @var string ARROW_FN
     */
    private const ARROW_FN = '(?:static\s+)?fn\s*\([^)]*\)\s*(?::\s*' . self::TYPE_PATTERN . ')?\s*=>';

    /**
     * Test whether a string is a serialized closure function expression.
     *
     * Supports optional static arrow and closure functions and optional return types.
     *
     * @var string ARROW_FN
     */
    private const SERIALIZED_CLOSURE_TEST = '/s:4:"body";s:\d+:"\s*(?:' .
        self::CLOSURE_FN . '|' .
        self::ARROW_FN .
    ')/s';

    /**
     * Match a full named closure function expression.
     *
     * Supports optional static closures, parameters, use variables,
     * return types, and closure body extraction.
     *
     * Examples:
     *
     * - `function () {}`
     * - `static function ($value) use ($item): string {}`
     *
     * @var string CLOSURE_FN_CAPTURE
     */
    private const CLOSURE_FN_CAPTURE = '/^(static\s+)?function\s*\((.*?)\)\s*(?:use\s*\((.*?)\))?\s*(?::\s*(' 
        . self::TYPE_PATTERN . 
    '))?\s*\{(.*?)\}\s*;?\s*$/s';

    /**
     * Match a full arrow function expression.
     *
     * Supports optional static arrow functions, parameters,
     * return types, and expression body extraction.
     *
     * Examples:
     *
     * - `fn($value) => $value`
     * - `static fn(): string => 'value'`
     *
     * @var string ARROW_FN_CAPTURE
     * // (?:static\s+) For none capture static
     */
    private const ARROW_FN_CAPTURE = '/^(static\s+)?fn\s*\((.*?)\)\s*(?::\s*(' 
        . self::TYPE_PATTERN . 
    '))?\s*=>\s*(.*)$/s';

    /**
     * Extract a closure function expression from serialized code.
     *
     * Supports optional static closures, use declarations,
     * return types, and closure bodies.
     *
     * @var string CLOSURE_EXTRACT
     */
    private const CLOSURE_EXTRACT = '/^\s*((?:static\s+)?function\s*\(.*?\)\s*(?:use\s*\(.*?\))?\s*(?::\s*' . 
        self::TYPE_PATTERN . 
    ')?\s*\{.*?\})/s';

    /**
     * Extract an arrow function expression from serialized code.
     *
     * Supports optional static arrow functions, return types,
     * and ignores trailing serialized metadata.
     *
     * @var string ARROW_EXTRACT
     */
    private const ARROW_EXTRACT = '/^\s*((?:static\s+)?fn\s*\(.*?\)\s*(?::\s*' . 
        self::TYPE_PATTERN . 
    ')?\s*=>\s*.+?)(?:,\s*.*)?$/s';

    /**
     * @var array<int,object> $registry
     */
    private static array $registry = [];

    /**
     * @var array<string,Closure> $cache
     */
    private static array $cache = [];

    /**
     * Private constructor to prevent direct instantiation.
     *
     * @param array $payload The closure data payload.
     */
    private function __construct(private array $payload) {}

    /**
     * Returns the data to be serialized.
     *
     * @return array Returns the data to be serialized.
     */
    public function __serialize(): array
    {
        return $this->payload;
    }

    /**
     * Awake the object from serialized data.
     *
     * @param array $data The data to unserialize.
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $this->payload = $data;
    }

    /**
     * Serializes a closure into a storable string representation.
     *
     * This method converts a Closure into a serialized payload containing its source
     * code, captured variables, scope information, and bound object context when
     * available. The generated data can later be restored to recreate the closure.
     *
     * An optional HMAC signature can be added to the payload to verify its
     * integrity and prevent unauthorized modification before deserialization.
     *
     * Supports both regular and arrow functions, including closures that capture
     * variables from their surrounding scope.
     *
     * @param Closure $closure Closure to serialize.
     * @param bool $sign Whether to include a security signature in the serialized payload.
     * @param string|null $key Optional sign key of closure authentication and verification.
     *                          If `null` default to `env(app.key)`, when `$sign` is true.
     *
     * @return string Serialized closure representation.
     *
     * @throws RuntimeException If the closure cannot be serialized due to unsupported
     *                          captured values or extraction errors.
     *
     * @example - Serializing a simple closure:
     * ```php
     * $fn = function ($name) {
     *     return "Hello, {$name}!";
     * };
     *
     * $serialized = Serializer::serialize($fn);
     * ```
     */
    public static function serialize(
        Closure $closure, 
        bool $sign = true,
        ?string $key = null
    ): string
    {
        try{
            $payload = self::encode($closure);

            if($sign){
                $payload['sig'] = self::sign($payload, $key);
            }

            return serialize(new self($payload));
        } catch (Throwable $e) {
            self::error($e, depth: 2);
        }

        return '';
    }

    /**
     * Restores a closure from a serialized string representation.
     *
     * This method reverses the serialization process by validating and parsing the
     * serialized closure payload, restoring captured variables, scope information,
     * and object binding when available, then recreating the original Closure
     * instance.
     *
     * The restored closure is rebuilt using the original execution context and
     * cached internally to avoid unnecessary recreation of identical closures.
     *
     * Optional signature verification settings can be provided to validate the
     * integrity of signed closure data before restoration.
     *
     * @param string $serialized Serialized closure payload generated by {@see self::serialize()}.
     * @param array{key?:?string} $options Optional unserialize options:
     *     - `key` - Optional sign key of serialized closure verification.
     *
     * @return Closure|null Restored Closure instance, or null when the input is not
     *                      a valid serialized closure.
     *
     * @throws RuntimeException If the serialized closure format is invalid,
     *                          signature verification fails, or restoration fails.
     *
     * @example - Restoring a serialized closure:
     * ```php
     * $fn = function ($name) {
     *     return "Hello, {$name}!";
     * };
     *
     * $serialized = Serializer::serialize($fn);
     * $closure = Serializer::unserialize($serialized);
     *
     * echo $closure('World'); // Hello, World!
     * ```
     */
    public static function unserialize(string $serialized, array $options = []): ?Closure
    {
        if(!self::isClosure($serialized)){
            return null;
        }

        $data = self::parse($serialized, $options);
        $head = $data['head'] ?? [];

        $key = Luminova::hash('xxh128', $data['sig'] ?? $serialized, fallbackAlgo: 'md5');

        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        return self::decode(
            $data['body'],
            $data['use'],
            $data['scope'],
            $data['this'],
            $head['file'] ?? null,
            $head['line'] ?? null,
            $key
        );
    }

    /**
     * Determines whether a serialized string contains a closure payload.
     *
     * This method checks whether the serialized data was generated by this
     * serializer by validating the serialized object signature format.
     *
     * It does not fully deserialize or validate the closure payload, it only
     * performs a lightweight format check {@see self::isValid()}.
     *
     * @param string $serialized Serialized data to inspect.
     *
     * @return bool True if the serialized string matches the closure payload format,
     *              otherwise false.
     */
    public static function isClosure(string $serialized): bool
    {
        if(!$serialized){
            return false;
        }

        $class = self::class;
        $length = strlen($class);

        if(!str_starts_with($serialized, "O:{$length}:\"{$class}\"")){
            return false;
        }

        return preg_match(self::SERIALIZED_CLOSURE_TEST, $serialized) === 1;
    }

    /**
     * Validates a serialized closure payload and its optional signature.
     *
     * This method verifies that the serialized data contains a valid closure
     * payload, safely restores the serializer object, and checks the payload
     * integrity using its stored signature when applicable.
     *
     * The signing key can be provided through options or resolved from the
     * application configuration when required.
     *
     * @param string $serialized Serialized closure payload to validate.
     * @param array<string,mixed> $options Validation options:
     *      - `key` Signing key used to verify the payload signature.
     *
     * @return bool True if the serialized closure payload is valid, otherwise false.
     *
     * @throws RuntimeException If signature verification requires a key that is not available.
     */
    public static function isValid(string $serialized, array $options = []): bool
    {
        if(!self::isClosure($serialized)){
            return false;
        }

        $key = $options['key'] ?? null;

        unset($options['key']);

        $data = unserialize(
            $serialized, 
            ['allowed_classes' => [self::class]]
        );

        return self::validate($data->payload, $key);
    }

    /**
     * Encodes a closure into a serializable representation.
     *
     * Extracts the closure source code, captured variables, scope information, and
     * bound object reference required to rebuild the closure later. The generated
     * payload preserves the original execution context, including namespace,
     * class scope, and `$this` binding when available.
     *
     * The closure code is processed to replace PHP magic constants with their
     * original resolved values, and captured variables are converted into metadata
     * that can be restored during decoding.
     *
     * @param Closure $closure Closure instance to encode.
     *
     * @return array Serializable closure payload.
     *
     * @throws RuntimeException If the closure source code cannot be extracted.
     */
    private static function encode(Closure $closure): array
    {
        $reflection = new ReflectionFunction($closure);
        
        [$code, $namespace] = self::extract($reflection);

        if(!$code){
            throw new RuntimeException('Failed to serialize closure.');
        }

        $code = self::normalize($code);

        try{
            $id = null;
            $class = null;
            $scope = $reflection->getClosureScopeClass();
            $thisObject = $reflection->getClosureThis();
            $filename = $reflection->getFileName();

            if ($thisObject !== null) {
                $id = Luminova::hash(
                    'xxh3', 
                    spl_object_id($closure) . ':' . spl_object_id($thisObject),
                    fallbackAlgo: 'md5'
                );
                self::$registry[$id] = $thisObject;
            }

            if($scope){
                $class = $scope->getName();
                $namespace = $scope->getNamespaceName();
            }

            $payload = [
                'head'    => [
                    'ver'  => self::VERSION,
                    'ns'   => trim((string) $namespace),
                ],
                'sig'     => null,
                'body'    => self::replaceMagicConstants(
                    $code, 
                    $reflection, 
                    $filename, 
                    $class, 
                    $namespace
                ),
                'use'     => self::getUseVariables($reflection),
                'scope'   => (string) $class,
                'this'    => $id,
            ];

            if(!PRODUCTION){
                $payload['head']['file'] = $reflection->getFileName();
                $payload['head']['line'] = $reflection->getStartLine();
            }

            return $payload;
        } finally {
            $scope = null;
            $reflection = null;
            $thisObject = null;
        }
    }

    /**
     * Normalize the serialized closure string ending.
     *
     * Removes serialization artifacts such as trailing separators and
     * extra closing brackets added when a closure is passed directly
     * as an argument to a serializer.
     *
     * Handles both traditional closures and arrow functions:
     *
     * - Normal closures: `function () {}`
     * - Arrow functions: `fn() => new Class()`
     *
     * Example:
     *
     * Input:
     * `fn() => new \stdClass())`
     *
     * Output:
     * `fn() => new \stdClass()`
     *
     * @param string $code The serialized closure code.
     * @param bool   $isArrow Whether the closure is an arrow function.
     *
     * @return string The normalized closure code.
     */
    private static function normalizedEnd(string $code, bool $isArrow = false): string
    {
        // Remove trailing separators but preserve internal formatting.
        $code = rtrim($code, " \t\n\r\0\x0B;,");

        // Normal closure already ends correctly.
        if (!$isArrow && str_ends_with($code, '}')) {
            return $code;
        }

        // Handle direct serialize(fn() => new Class()) case:
        // fn() => new \stdClass()
        // becomes:
        // fn() => new \stdClass()
        //          )
        if ($isArrow && preg_match('/\R\s*\)\s*$/', $code)) {
            $code = preg_replace('/\R\s*\)\s*$/', '', $code);
            return rtrim($code);
        }

        // Count only when the end looks suspicious.
        $open  = substr_count($code, '(');
        $close = substr_count($code, ')');

        if ($close > $open && $close - $open === 1) {
            // Arrow function has one extra bracket.
            // Normal closure has ");" wrapper.
            $code = substr($code, 0, -($isArrow ? 1 : 2));
        }

        return rtrim($code, " \t\n\r\0\x0B;,");
    }

    /**
     * Normalize serialized closure code.
     *
     * Extracts the closure expression from serialized output and removes
     * serialization artifacts such as trailing separators or extra closing
     * brackets added when closures are passed directly as arguments.
     *
     * Supports:
     *
     * - Traditional closures: `function () {}`
     * - Arrow functions: `fn() => expression`
     *
     * If the code cannot be identified as a supported closure type, an
     * exception is thrown.
     *
     * @param string $code Serialized closure code.
     *
     * @return string Normalized closure code.
     *
     * @throws RuntimeException When the code is not a valid closure expression.
     */
    private static function normalize(string $code): string
    {
        $match = [];

        if (preg_match(self::CLOSURE_EXTRACT, $code, $match) === 1) {
            return self::normalizedEnd($match[1], false);
        }

        if (preg_match(self::ARROW_EXTRACT, $code, $match) === 1) {
            return self::normalizedEnd($match[1], true);
        }

        throw new RuntimeException('Incorrect closure code, failed serializing.');
    }

    /**
     * Replaces PHP magic constants in closure code with resolved values.
     *
     * This method processes closure source code and replaces supported magic
     * constants with the values from the closure's original execution context.
     * This allows rebuilt closures to preserve references to their original file,
     * directory, class, method, and namespace information.
     *
     * Supported constants:
     * - `__DIR__`
     * - `__FILE__`
     * - `__CLASS__`
     * - `__METHOD__`
     * - `__NAMESPACE__`
     *
     * Constants that cannot be resolved are left unchanged.
     *
     * @param string $code Closure code to process.
     * @param ReflectionFunction $reflection Reflection instance of the closure.
     * @param string|null $filename File where the closure was defined.
     * @param string|null $class Class name associated with the closure.
     * @param string|null $namespace Namespace where the closure was defined.
     *
     * @return string Closure code with resolved magic constants.
     */
    private static function replaceMagicConstants(
        string $code,
        ReflectionFunction $reflection,
        ?string $filename = null,
        ?string $class = null,
        ?string $namespace = null
    ): string 
    {
        $replace = [];

        if ($filename) {
            if (str_contains($code, '__DIR__')) {
                $replace['__DIR__'] = var_export(dirname($filename), true);
            }

            if (str_contains($code, '__FILE__')) {
                $replace['__FILE__'] = var_export($filename, true);
            }
        }

        if ($class && str_contains($code, '__CLASS__')) {
            $replace['__CLASS__'] = var_export($class, true);
        }

        if (str_contains($code, '__METHOD__')) {
            $replace['__METHOD__'] = var_export($reflection->getName(), true);
        }

        if ($namespace && str_contains($code, '__NAMESPACE__')) {
            $replace['__NAMESPACE__'] = var_export($namespace, true);
        }

        if ($replace === []) {
            return $code;
        }

        return str_replace(
            array_keys($replace), 
            array_values($replace), 
            $code
        );
    }

    /**
     * Parse the serialized closure string and extract the payload data.
     *
     * @param string $serialized
     * @param array{key?:?string} $options Optional unserialize options:
     *     - `key` - Optional sign key of serialized closure verification.
     * 
     * 
     * @return array Returns the extracted payload data from the serialized closure.
     * @throws RuntimeException If the signature verification fails or if the serialized data is invalid.
     */
    private static function parse(string $serialized, array $options): array
    {
        $key = $options['key'] ?? null;

        unset($options['key']);
        $data = unserialize($serialized, [
            'allowed_classes' => [self::class]
        ]);

        if(!self::validate($data->payload, $key)){
            self::error(
                new RuntimeException('Closure signature verification failed.'), 
                depth: 3
            );
        }

        return $data->payload;
    }

    /**
     * Validate closure signature.
     *
     * @param array $data The closure data payload to validate.
     * @param string|null $key The key to use for signature verification. If null, 
     *          the method will attempt to use the application key from the environment variables.
     * 
     * @return bool Returns true if the signature is valid or if no signature is present, 
     *      false if the signature is invalid.
     */
    private static function validate(array $data, ?string $key): bool
    {
        if($data === []){
            return false;
        }

        $sig = $data['sig'] ?? null;

        if($sig === null){
            return true;
        }

        $key = self::generateSignature($data, $key);

        if(!$key){
            self::error(new RuntimeException(
                'Application key is required to unserialize secured closure.'
            ), depth: 4);
        }

        $data['sig'] = null;
        $sig = base64_decode($sig);
        $expected = hash_hmac(self::SIGN_ALGO, serialize($data), $key, true);

        return hash_equals($expected, $sig);
    }

    /**
     * Generates a cryptographic signature for serialized closure payload data.
     *
     * The payload is serialized and signed using an HMAC algorithm with the
     * application signing key. The generated binary signature is encoded as
     * Base64 for safe storage or transmission.
     *
     * This signature is used to verify the integrity and authenticity of serialized
     * closure data before it is restored or executed.
     *
     * @param array $payload Data payload to sign.
     *
     * @return string Base64-encoded HMAC signature.
     *
     * @throws RuntimeException If no application signing key is available.
     */
    private static function sign(array $payload, ?string $key = null): string 
    {
        $key = self::generateSignature($payload, $key);

        if(!$key){
            throw new RuntimeException(
                'Application key is required to serialize closure with security.'
            );
        }

        $data = serialize($payload);

        return base64_encode(hash_hmac(
            self::SIGN_ALGO, 
            $data, 
            $key, 
            true
        ));
    }

    /**
     * Generate sign key.
     * 
     * @param array $data Closure payload for additionally key entropy.
     * @param string|null $key Optional base key.
     * 
     * @return string|null Return generate key or null if based key is not found.
     */
    private static function generateSignature(array $data, ?string $key = null): ?string
    {
        $key ??= env('app.key');

        if (!$key) {
            return null;
        }

        $id = $data['this'] ?? '';
        $body = $data['body'] ?? '';
        $vars  = isset($data['use']) ? serialize($data['use']) : '';

        $scope = $data['scope'] ?? '';
        $namespace = $data['head']['ns'] ?? '';

        return Luminova::hash(
            'xxh128',
            $key . $namespace . $scope . $id . $body . $vars,
            fallbackAlgo: 'md5'
        );
    }

    /**
     * Extract closure source code and namespace from reflection.
     *
     * Reads the closure location from its source file, tokenizes the source,
     * and extracts the function or arrow function declaration.
     *
     * @param ReflectionFunction $reflection Closure reflection instance.
     *
     * @return array{0:?string,1:?string} code, namespace
     */

    private static function extract(ReflectionFunction $reflection): array
    {
        $lines = self::findLines($reflection);

        if ($lines === null) {
            return [null, null];
        }

        $tokens = PhpToken::tokenize('<?php ' . implode('', $lines));

        $capture = false;
        $namespace = '';
        $readingNamespace = false;
        $result = '';

        foreach ($tokens as $token) {
            if ($token->is(T_NAMESPACE)) {
                $readingNamespace = true;
            }

            if ($readingNamespace) {
                $namespace .= $token->text;

                if ($token->is(';')) {
                    $readingNamespace = false;
                }
            }

            if (!$capture) {
                if ($token->is([T_FUNCTION, T_FN])) {
                    $capture = true;
                } elseif ($token->is([T_STATIC])) {
                    $capture = true;
                } elseif ($token->isIgnorable()) {
                    continue;
                }
            }

            if ($capture) {
                $result .= $token->text;
            }
        }

        return [
            trim($result) ?: null,
            $namespace ? trim($namespace, ";\t\n\r ") : null,
        ];
    }

    /**
     * Read closure source lines from its defining file.
     *
     * @param ReflectionFunction $reflection Closure reflection instance.
     *
     * @return array<int,string>|null Source lines or null when unavailable.
     */
    private static function findLines(ReflectionFunction $reflection): ?array
    {
        $file = $reflection->getFileName();

        if ($file === false || ($lines = file($file)) === false) {
            return null;
        }

        $start = $reflection->getStartLine();
        $end = $reflection->getEndLine();

        return array_slice(
            $lines, 
            $start - 1, 
            $end - $start + 1
        );
    }

    /**
     * Create closure from code string.
     *
     * @param string $code Closure code body.
     * @param array $vars Closure variables.
     * @param string|null $scope Closure class scope.
     * @param string|null $self Closure this new scope.
     * @param string $file Closure filename.
     * @param int $line Closure file line.
     * @param string|null $key Cache key.
     * 
     * @return Closure
     */
    private static function decode(
        string $code, 
        array $vars, 
        ?string $scope, 
        ?string $self,
        ?string $file, 
        ?int $line,
        ?string $key
    ): Closure 
    {
        $cwd = null;
        [$code, $isStatic] = self::buildFunction(
            $code, 
            $vars,
            $file,
            $line
        );
        
        if($file && is_file($file)){
            $cwd = getcwd();
            $file = dirname($file);

            if (is_dir($file)) {
                chdir($file);
            }
        }
        
        try {
            $closure = eval($code);
        } catch (ParseError|Throwable $e) {
            self::error($e, depth: 3);
        } finally {
            if($cwd && $file){
                chdir($cwd);
            }
        }
        
        if (!$closure instanceof Closure) {
            self::error(
                new RuntimeException("Failed to create closure from code"), 
                depth: 3
            );
        }

        return self::$cache[$key] = $isStatic
            ? Closure::bind($closure, null, $scope)
            : Closure::bind($closure, self::$registry[$self] ?? null, $scope);
    }

    /**
     * Builds executable closure code from a function string.
     *
     * Extracted variables are converted into closure assignments and `use` bindings
     * when required, including reference handling for captured variables.
     *
     * @param string $code Function or closure definition code.
     * @param array $vars Variables available for injection into the generated closure.
     *
     * @return array{0:string,1:bool} Generated closure code ready for evaluation.
     * @throws RuntimeException If the closure body is empty or invalid.
     */
    private static function buildFunction(
        string $code, 
        array $vars,
        ?string $file = null, 
        ?int $line = null,
    ): array 
    {
        $code = rtrim($code, " \t\n\r\0\x0B;,");
        $use = '';
        $body = null;
        $isStatic = false;
        $assign = '';
        $return = '';
        $params = '';
        $assignments = '';
        $reference = [];

        if (preg_match(self::ARROW_FN_CAPTURE, $code, $matches)) {
            $isStatic = trim($matches[1]) === 'static';
            $params   = trim($matches[2]);
            $return   = trim($matches[3] ?? '');
            $raw      = rtrim(trim($matches[4]), ';');

            if ($raw === '') {
                self::error(
                    new RuntimeException("Invalid arrow function body"),
                    depth: 4
                );
            }

            $body = "return {$raw}";
    
            [$assignments, $use, $assign] = self::getUseGlobals(
                $vars, 
                $reference, 
                true
            );
        } elseif (preg_match(self::CLOSURE_FN_CAPTURE, $code, $matches)) {
            $isStatic = trim($matches[1]) === 'static';
            $params   = trim($matches[2]);
            $use      = trim($matches[3] ?? '');
            $return   = trim($matches[4] ?? '');
            $body     = rtrim(trim($matches[5]), ';');

            $reference = str_contains($use, '&$') 
                ? self::getReference($use)
                : [];

            [$assignments,,$assign] = self::getUseGlobals(
                $vars, 
                $reference, 
                false
            );
        }
        if($body === null) {
            self::error(
                new RuntimeException("Invalid closure code format"), 
                depth: 4
            );
        }

        return [
            self::phpEvalHandler(
                $assignments,
                $assign,
                $params,
                $use,
                $return,
                $body,
                $isStatic,
                $file,
                $line
            ), 
            $isStatic
        ];
    }

    /**
     * Generate the PHP code used to reconstruct and execute a closure.
     *
     * The generated closure wraps the original body in a `try/catch` block to
     * preserve debugging information. Framework exceptions are recreated with
     * the resolved source location, while other exceptions are converted into
     * `ErrorException` instances with traced file and line information.
     *
     * @param string      $assignments PHP statements that initialize captured variables.
     * @param string      $assign      Closure assignment target.
     * @param string      $params      Closure parameter declaration.
     * @param string      $use         Variables captured by the closure.
     * @param string      $return      Closure return type declaration.
     * @param string      $body        Closure executable body.
     * @param bool        $isStatic    Whether to generate a static closure.
     * @param string|null $source      Original closure source file, if available.
     * @param int|null    $line        Original closure source line, if available.
     *
     * @return string Generated PHP closure code.
     */
    private static function phpEvalHandler(
        string $assignments,
        string $assign,
        string $params,
        string $use,
        string $return,
        string $body,
        bool $isStatic = false,
        ?string $source = null,
        ?int $line = null,
    ): string
    {
        $return = ($return !== '') ? ": {$return}" : '';
        $use    = ($use !== '') ? " use ({$use})" : '';
        $static = $isStatic ? ' static ' : ' ';
        $source = $source ?? '';
        $line ??= 0;

        return <<<PHP
        {$assignments} return {$assign}{$static}function({$params}){$use}{$return} {
            try {
                {$body};
            } catch (Throwable \$e) {
                [\$file, \$line] = \\Luminova\\Debugger\\Tracer::trace(1);
                \$message = \$e->getMessage();
                \$name = null;

                if ('{$source}' !== '') {
                    \$name = basename('{$source}');

                    if (is_file('{$source}')) {
                        \$message .= ".\\nClosure source: {$source}:{$line}";
                    }
                }

                if (!\$file) {
                    \$file = ' : anonymous-function@' . (\$name ?? uniqid());
                }

                if (!\$e instanceof \\Luminova\\Exceptions\\LuminovaException) {
                    throw new \\ErrorException(
                        \$message,
                        \$e->getCode(),
                        1,
                        \$file,
                        \$line,
                        \$e
                    );
                }

                \$class = get_class(\$e);

                throw (new \$class(\$message, \$e->getCode(), previous: \$e))
                    ->setFile(\$file)
                    ->setLine(\$line);
            }
        };
        PHP;
    }

    /**
     * Extracts referenced variables from a closure `use` declaration.
     *
     * Detects variables captured by reference and returns their names so they can
     * be recreated with reference bindings when rebuilding the closure.
     *
     * @param string $use Closure `use` variable declaration content.
     *
     * @return array List of referenced variable names.
     */
    private static function getReference(string $use): array
    {
        $references = [];

        foreach (explode(',', $use) as $part) {
            $part = trim($part);

            if (!str_starts_with($part, '&')) {
                continue;
            }

            $name = ltrim($part, '&$ ');
            $references[$name] = true;
        }

        return $references;
    }

    /**
     * Extracts captured variables from a reflected closure.
     *
     * Captured values are converted into metadata containing their type and
     * serialized value so they can be restored when rebuilding the closure.
     *
     * Supported captured values:
     * - Closures (stored as placeholders for reassignment).
     * - Objects (serialized and restored).
     * - Scalars, arrays, and null values.
     *
     * Unsupported values trigger an exception.
     *
     * @param ReflectionFunction $reflection Closure reflection instance.
     *
     * @return array Captured variable metadata.
     */
    private static function getUseVariables(ReflectionFunction $reflection): array
    {
        $vars = [];

        foreach ($reflection->getStaticVariables() as $name => $value) {
            if ($value instanceof Closure) {
                $vars[$name] = [
                    't'  => 3,
                    'v'  => null,
                ];
                continue;
            }

            if (is_object($value)) {
                try {
                    $vars[$name] = [
                        't'  => 2,
                        'v'  => serialize($value),
                    ];
                } catch (Throwable $e) {
                    self::error(new RuntimeException(
                        "Cannot serialize captured variable \${$name} of type: " . get_debug_type($value),
                        0,
                        $e
                    ));
                }

                continue;
            }

            if (is_scalar($value) || is_null($value) || is_array($value)) {
                $vars[$name] = [
                    't'  => 1,
                    'v'  => $value,
                ];
                continue;
            }

            self::error(new RuntimeException(
                "Cannot capture variable \${$name} of type: " . get_debug_type($value)
            ));
        }

        return $vars;
    }

    /**
     * Generates variable assignments and `use` bindings for a rebuilt closure.
     *
     * Converts captured variables into executable PHP assignments and prepares the
     * `use` list required by arrow functions. Reference captures are restored using
     * variable references to preserve the original closure behavior.
     *
     * @param array $vars Captured variable metadata.
     * @param array $reference Variables captured by reference.
     * @param bool $isArrowFunction Whether the closure is an arrow function.
     *
     * @return array{0:string,1:string,2:string} Generated assignments, use bindings, 
     *      and closure assignment target.
     */
    private static function getUseGlobals(
        array $vars,
        array $reference = [],
        bool $isArrowFunction = false
    ): array 
    {
        $globals = [];
        $assignments = '';
        $assign = '';

        foreach ($vars as $name => $meta) {
            $byRef = $reference[$name] ?? false;

            if ($isArrowFunction) {
                $globals[$name] = $byRef ? "&\${$name}" : "\${$name}";
            }

            if ($meta['t'] === 3) {
                $assign = "\${$name} = ";
                continue;
            }

            $value = var_export($meta['v'], true);

            if ($meta['t'] === 2) {
                $value = "unserialize({$value}, ['allowed_classes' => true])";
            }

            if ($byRef) {
                $tmp = "__ref_{$name}";
                $assignments .= "\${$tmp} = {$value}; ";
                $assignments .= "\${$name} =& \${$tmp}; ";
                continue;
            }

            $assignments .= "\${$name} = {$value}; ";
        }

        return $isArrowFunction
            ? [$assignments, implode(', ', $globals), $assign]
            : [$assignments, '', $assign];
    }

    /**
     * Converts and throws an exception with normalized source information.
     *
     * Ensures all errors are represented as `RuntimeException` instances, attaches
     * the originating file and line information when available, and rethrows the
     * exception.
     *
     * @param Throwable $e Exception or error to normalize.
     * @param string|null $file Source file override.
     * @param int|null $line Source line override.
     * @param int $depth Trace depth used to locate the original caller.
     *
     * @return void
     *
     * @throws RuntimeException
     */
    private static function error(
        Throwable $e,
        ?string $file = null, 
        ?int $line = null,
        int $depth = 2
    ): void
    {
        if(!$e instanceof RuntimeException){
            $e = new RuntimeException(
                $e->getMessage(), 
                $e->getCode(), 
                $e
            );
        }

        if(!$file){
            [$file, $line] = Tracer::trace($depth);
        }

        if($file){
            $e->setFile($file)->setLine($line);
        }

        throw $e;
    }
}