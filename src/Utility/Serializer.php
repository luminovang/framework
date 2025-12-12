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
use \ReflectionFunction;
use \Luminova\Exceptions\RuntimeException;

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
     * Match full function name.
     * 
     * @var string FUNCTION_PATTERN
     */
    private const FUNCTION_PATTERN = '/^function\s*\((.*?)\)\s*(?:use\s*\((.*?)\))?\s*(?::\s*([\\\\\w|?&]+))?\s*\{(.*?)\}\s*;?\s*$/s';

    /**
     * Match arrow function.
     * 
     * @var string FN_PATTERN
     */
    private const FN_PATTERN = '/^fn\s*\((.*?)\)\s*(?::\s*([\\\\\w|?]+))?\s*=>\s*(.*)$/s';

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
     * Serialize a closure to string.
     * 
     * This method converts a Closure into a string representation that captures its code,
     * scope, and any variables it uses. It handles both regular and arrow functions, and
     * supports closures that capture variables from their surrounding scope.
     * 
     * @param Closure $closure The closure to serialize.
     * @param bool $sign Whether to sign and include a security signature for the serialized closure. 
     * 
     * @return string Returns the serialized string representation of the closure.
     * @throws RuntimeException If the closure cannot be serialized due to unsupported variable types or other issues.
     * 
     * @example - Serializing a simple closure:
     * ```php
     * $fn = function($name) {
     *     return "Hello, {$name}!";
     * };
     * $serialized = Serializer::serialize($fn);
     * echo $serialized; // Outputs a serialized string representation of the closure.
     * ```
     */
    public static function serialize(Closure $closure, bool $sign = true): string
    {
        try{
            $reflection = new ReflectionFunction($closure);
            
            [$code, $namespace] = self::getClosureCode($reflection);

            if($code === null){
                throw new RuntimeException('Failed to serialize closure.');
            }

            $id = null;
            $class = null;
            $scope = $reflection->getClosureScopeClass();
            $thisObject = $reflection->getClosureThis();
            $filename = $reflection->getFileName();

            if ($thisObject !== null) {
                $id = (spl_object_id($closure) + spl_object_id($thisObject));
                self::$registry[$id] = $thisObject;
            }

            if($scope){
                $class = $scope->getName();
                $namespace = $scope->getNamespaceName();
            }

            $payload = [
                'info'    => [
                    'version'     => self::VERSION,
                    'namespace'   => trim((string) $namespace ?? '@anonymous'),
                    // 'file'    => $reflection->getFileName(),
                    //'line'    => $reflection->getStartLine(),
                ],
                'sig'     => null,
                'code'    => self::replaceMagicConstants($code, $reflection, $filename, $class, $namespace),
                'use'     => self::getUseVariables($reflection),
                'scope'   => $class,
                'this'    => $id,
            ];

            if($sign){
                $key = self::getSignKey($payload);

                if(!$key){
                    throw new RuntimeException(
                        'Application key is required to serialize closure with security.'
                    );
                }

                $data = serialize($payload);

                $payload['sig'] = base64_encode(hash_hmac(
                    self::SIGN_ALGO, 
                    $data, 
                    $key, 
                    true
                ));
            }

            return serialize(new self($payload));
        } catch (Throwable $e) {
            self::error($e, depth: 2);
        } finally {
            $scope = null;
            $reflection = null;
            $thisObject = null;
        }

        return '';
    }
        
    /**
     * Replace PHP magic constants in closure code with their actual values.
     *
     * @param string $code The closure code as a string.
     * @param ReflectionFunction $reflection The reflection of the closure.
     * @param ?string $filename The file the closure was defined in.
     * @param ?string $class The class name the closure belongs to, if any.
     * @param ?string $namespace The namespace the closure belongs to, if any.
     *
     * @return string The code with magic constants replaced.
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

        // if ($class && str_contains($code, '__TRAIT__')) {
        //    $replace['__TRAIT__'] = var_export($class, true);
        // }

        if (str_contains($code, '__METHOD__')) {
            $replace['__METHOD__'] = var_export($reflection->getName(), true);
        }

        if ($namespace && str_contains($code, '__NAMESPACE__')) {
            $replace['__NAMESPACE__'] = var_export($namespace, true);
        }

        if ($replace === []) {
            return $code;
        }

        return str_replace(array_keys($replace), array_values($replace), $code);
    }

    /**
     * Unserialize a closure from string.
     * 
     * This method takes a serialized string representation of a closure (produced by the `serialize` method) 
     * and reconstructs the original Closure object. 
     * 
     * It evaluates the closure code in the correct scope and binds it to the appropriate object if necessary.
     * 
     * @param string $serialized The serialized string representation of the closure.
     * @param array<string,mixed> $options Optional unserialize options or application key for verifying signature:
     *      `key` - Optional sign key of the serialized closure verification.
     *      `allowed_classes` - Weather to allow class (default: true).
     * 
     * @return Closure|null Returns the unserialized Closure object, 
     *      or null if the input is invalid or cannot be unserialized.
     * @throws RuntimeException If the closure cannot be unserialized due to invalid format, 
     *      evaluation errors, or other issues.
     * 
     * @example - Unserializing a closure:
     * ```php    
     *  $fn = function($name) {
     *     return "Hello, {$name}!";
     * };
     * $serialized = Serializer::serialize($fn);
     * 
     * $closure = Serializer::unserialize($serialized);
     * echo $closure('World'); // Outputs: Hello, World!
     * ```
     */
    public static function unserialize(string $serialized, array $options = []): ?Closure
    {
        if(!self::isClosure($serialized)){
            return null;
        }

        $data = self::parse($serialized, $options);
        $info = $data['info'] ?? [];

        $key = hash('xxh128', $data['sig'] ?? $serialized);

        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        return self::create(
            $data['code'],
            $data['use'],
            $data['scope'],
            $data['this'],
            $info['file'] ?? null,
            $info['line'] ?? null,
            $key
        );
    }

    /**
     * Check if a serialized string is a closure.
     * 
     * This method checks if the given serialized string is a valid closure representation produced 
     * by the `serialize` method.
     * 
     * @param string $serialized The serialized string to check.
     * 
     * @return bool Returns true if the string is a valid serialized closure, false otherwise.
     */
    public static function isClosure(string $serialized): bool
    {
        if(!$serialized){
            return false;
        }

        $class = self::class;
        $length = strlen($class);

        return str_starts_with($serialized, "O:{$length}:\"{$class}\"");
    }

    /**
     * Verify if serialized closure signature is valid.
     * 
     * This method checks if the given serialized string is a valid closure representation produced 
     * by the `serialize` method and verifies signature if is signed..
     *
     * @param string $serialized The serialized closure string.
     * @param array<string,mixed> $options Optional unserialize options or application key for verifying signature:
     *      `key` - Optional sign key of the serialized closure verification.
     *      `allowed_classes` - Weather to allow class (default: true).
     * 
     * @return bool Return true if is valid closure, otherwise false.
     * @throws RuntimeException If closure is signed and not key was provided and no env(app.key).
     */
    public static function isValid(string $serialized, array $options = []): bool
    {
        if(!self::isClosure($serialized)){
            return false;
        }

        $key = $options['key'] ?? null;
        $options['allowed_classes'] ??= true;

        unset($options['key']);

        $data = unserialize(
            $serialized, 
            $options
        );

        return self::validate($data->payload, $key);
    }

    /**
     * Parse the serialized closure string and extract the payload data.
     *
     * @param string $serialized
     * @param array<string,mixed> $options Optional unserialize options or application key for verifying signature:
     *     `key` - Optional sign key of the serialized closure verification.
     *     `allowed_classes` - Weather to allow class (default: true).
     * 
     * 
     * @return array Returns the extracted payload data from the serialized closure.
     * @throws RuntimeException If the signature verification fails or if the serialized data is invalid.
     */
    private static function parse(string $serialized, array $options): array
    {
        $key = $options['key'] ?? null;
        $options['allowed_classes'] ??= true;

        unset($options['key']);
        $data = unserialize($serialized, $options);

        if(!self::validate($data->payload, $key)){
            self::error(new RuntimeException('Closure signature verification failed.'), depth: 3);
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

        $key = self::getSignKey($data, $key);

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
     * Generate sign key.
     * 
     * @param array $data Closure payload for additionally key entropy.
     * @param string|null $key Optional base key.
     * 
     * @return string|null Return generate key or null if based key is not found.
     */
    private static function getSignKey(array $data, ?string $key = null): ?string
    {
        $key ??= env('app.key');

        if (!$key) {
            return null;
        }

        $id = $data['this'] ?? '';
        $code = $data['code'] ?? '';
        $vars  = isset($data['use']) ? serialize($data['use']) : '';

        $scope = $data['scope'] ?? 'anonymous';
        $namespace = $data['info']['namespace'] ?? '@anonymous';

        return hash('xxh128', $key . $namespace . $scope . $id . $code . $vars);
    }
    
    /**
     * Get closure code as string.
     *
     * @param ReflectionFunction $reflection
     * 
     * @return array{code:?string,namespace:?string}
     */
    private static function getClosureCode(ReflectionFunction $reflection): array
    {
        $lines = self::getLines($reflection);

        if($lines === null){
            return [null, null];
        }

        $tokens = PhpToken::tokenize('<?php ' . trim(implode('', $lines)));
        $capture = false;
        $ns = true;
        $namespace = '';
        $result = '';
        
        foreach ($tokens as $token) {
            if ($ns && $token->is(T_NAMESPACE)) {
                $namespace .= $token->text;
            }

            if ($ns && $token->is(';')) {
                $ns = false;
            }

            if (!$capture && $token->isIgnorable()) {
                continue;
            }

            if ($token->is([T_FUNCTION, T_FN])) {
                $capture = true;
            }

            if ($capture) {
                $result .= $token->text;
            }
        }
        
        return [trim($result), $namespace ?: null];
    }

    /**
     * Get closure code lines from file.
     *
     * @param ReflectionFunction $ref
     * 
     * @return array
     */
    private static function getLines(ReflectionFunction $ref): ?array
    {
        $file = file($ref->getFileName());

        if($file === false){
            return null;
        }

        $start = $ref->getStartLine();
        $end = $ref->getEndLine();

        return array_slice($file, $start - 1, $end - $start + 1);
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
    private static function create(
        string $code, 
        array $vars, 
        ?string $scope, 
        ?string $self,
        ?string $file, 
        ?int $line,
        ?string $key
    ): Closure 
    {
        $code = self::buildFunction($code, $vars);
        
        if($file){
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
            if($file){
                chdir($cwd);
            }
        }
        
        if (!$closure instanceof Closure) {
            self::error(new RuntimeException("Failed to create closure from code"), depth: 3);
        }

        return self::$cache[$key] = Closure::bind(
            $closure, 
            self::$registry[$self] ?? null, 
            $scope
        );
    }

    /**
     * Build the closure code for evaluation.
     *
     * @param string $code
     * @param array $vars
     * 
     * @return string
     */
    private static function buildFunction(string $code, array $vars): string 
    {
        $code = rtrim($code, ';');
        $use = '';
        $body = null;
        $assignments = '';
        $assign = '';
        $reference = [];

        if (preg_match(self::FN_PATTERN, $code, $matches)) {
            $params = trim($matches[1]);
            $return = trim($matches[2] ?? '');

            $body = 'return ' . rtrim(trim($matches[3]), ';');

            [$assignments, $use, $assign] = self::getUseGlobals($vars, $reference, true);
        } elseif (preg_match(self::FUNCTION_PATTERN, $code, $matches)) {
            $params = $matches[1];
            $return = trim($matches[3] ?? '');

            $use = isset($matches[2]) ? $matches[2] : '';
            $body = rtrim(trim($matches[4]), ';');

            $reference = str_contains($use, '&$') 
                ? self::getReference($use)
                : [];

            [$assignments,,$assign] = self::getUseGlobals($vars, $reference, false);
        }
        
        if(!$body) {
            self::error(new RuntimeException("Invalid closure code format"), depth: 4);
        }

        $return = $return ? ": {$return}" : '';
        $use = $use ? ' use (' . $use . ')' : '';
    
        return <<<PHP
        {$assignments} return {$assign}function({$params}){$use}{$return} {
            try {
                {$body};
            } catch(Throwable \$e){
                [\$file, \$line] = \\Luminova\\Exceptions\\LuminovaException::trace(1);

                if(!\$file){
                    \$file = uniqid(' : anonymous-function-');
                }

                if(!\$e instanceof \\Luminova\\Exceptions\\LuminovaException){
                    throw new \\ErrorException(
                        \$e->getMessage(),
                        \$e->getCode(),
                        1,
                        \$file,
                        \$line,
                        \$e
                    );
                }

                \$e->setFile(\$file)->setLine(\$line);

                throw \$e;
            }
        };
        PHP;
    }

    /**
     * Undocumented function
     *
     * @param string $use
     * @return array
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
     * Get use variables from closure
     *
     * @param ReflectionFunction $reflection
     * 
     * @return array
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
     * Return use variables as global assignments and use list for arrow functions.
     *
     * @param array $vars
     * @param bool $isArrowFunction
     * 
     * @return array
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
                $value = "unserialize(" . $value . ", ['allowed_classes' => true])";
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
     * 
     * @return void
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
            [$file, $line] = RuntimeException::trace($depth);
        }

        if($file){
            $e->setFile($file)->setLine($line);
        }

        throw $e;
    }
}