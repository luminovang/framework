<?php 
declare(strict_types=1);
/**
 * Luminova Framework JSON Web Token (JWT) authentication.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Security;

use \Closure;
use \stdClass;
use \Throwable;
use \Firebase\JWT\Key;
use Luminova\Luminova;
use Luminova\Time\Time;
use \OpenSSLCertificate;
use \OpenSSLAsymmetricKey;
use Luminova\Logger\Logger;
use \Firebase\JWT\JWT as Token;
use Luminova\Interface\LazyObjectInterface;
use Luminova\Exceptions\{ErrorCode, LuminovaException, EncryptionException, InvalidArgumentException};
use function Luminova\Funcs\{
    root,
    write_content,
    make_dir
};

/**
 * JWT helper class.
 * 
 * @phpstan-type PropertyName = 'uid'|'sub'|'role'|'jti'|'iss'|'aud'|'iat'|'exp'|'fmv'|'app'|'ver'|'data'
 */
final class JWT implements LazyObjectInterface
{
    /**
     * Shared instance of the JWT.
     * 
     * @var static|null $instance
     */
	private static ?self $instance = null;

    /**
     * Validation error.
     * 
     * @var object|null $error
     */
    private ?object $error = null;

    /**
     * JWT payload.
     * 
     * @var object|null $payload
     */
    private ?object $payload = null;

    /**
     * JWT sign key.
     * 
     * @var OpenSSLAsymmetricKey|OpenSSLCertificate|string|null $key
     */
    private OpenSSLAsymmetricKey|OpenSSLCertificate|string|null $key = null;

    /**
     * JWT key base path.
     * 
     * @var string|null $path
     */
    private static ?string $path = null;

    /**
     * JWT users cache.
     * 
     * @var array<string,array{file:?string,token:?string}> $cache
     */
    private static array $cache = [];

    /**
     * Create a new JWT instance with custom signing and validation settings.
     *
     * Supports the following algorithms: ES256, ES384, HS256, HS384, HS512, RS256, RS384, and RS512.
     *
     * @param string|null $algo JWT signing algorithm (default: HS256).
     * @param string|null $salt Optional key prefix or salt.
     * @param string|null $iss JWT issuer claim (default: APP_URL).
     * @param string|null $aud JWT audience claim (default: APP_URL . '/api').
     *
     * @link https://luminova.ng/docs/0.0.0/security/jwt
     * 
     * > **Note:**
     * > All file stored keys are located in '/writeable/auth/jwt'.
     */
    public function __construct(
        protected ?string $algo = null,
        protected ?string $salt = null,
        protected ?string $iss = null, 
        protected ?string $aud = null
    )
	{
        $this->error = null;
        $this->payload = null;
    }

    /**
     * Get the shared JWT instance, creating it if it does not already exist.
     *
     * Supports the following algorithms: ES256, ES384, HS256, HS384, HS512, RS256, RS384, and RS512.
     *
     * @param string|null $algo JWT signing algorithm (default: HS256).
     * @param string|null $salt Optional key prefix or salt.
     * @param string|null $iss JWT issuer claim (default: APP_URL).
     * @param string|null $aud JWT audience claim (default: APP_URL . '/api').
     *
     * @return self Return a shared JWT instance.
     * 
     * @link https://luminova.ng/docs/0.0.0/security/jwt
     * 
     *  > **Note:**
     * > All file stored keys are located in '/writeable/auth/jwt'.
     */
    public static function getInstance(
        ?string $algo = null,
        ?string $salt = null,
        ?string $iss = null, 
        ?string $aud = null
    ): self 
    {
        if(!self::$instance instanceof static){
            self::$instance = new self($algo, $salt, $iss, $aud);
        }

        return self::$instance;
    }

    /**
     * Validate token and user ID.
     * 
     * @param string|int $userId The user identifier, which should match the `userId` claim in the token.
     * @param string|null $token The auth token, typically in the format `scheme token` (e.g., `Bearer my-token`).
     *              If null read token from file based on user ID.
     *
     * @return bool Returns `true` if the authentication is valid, otherwise `false`.
     * 
     * @example - Example:
     * ```php
     * $isValid = JWT::isValidToken('Bearer my-token', 'user-id');
     * 
     * if ($isValid) {
     *     // Authentication is valid
     * } else {
     *     // Authentication failed
     * }
     * ```
     */
    public static function isValidToken(string|int $userId, ?string $token = null): bool 
    {
        if($userId === ''){
            return false;
        }

        try{
            $instance = self::getInstance();
            $result = $instance->validate($token, $userId);
        } catch(Throwable){
            return false;
        }

        return $instance->hasError() 
            ? false 
            : $result;
    }

    /**
     * Check JWT produce validation error.
     *
     * @return bool Return true if error otherwise false.
     */
    public function hasError(): bool 
    {
        return $this->error !== null;
    }

    /**
     * Retrieves the validation error object.
     * 
     * This method returns the error details about the last JWT operation,
     * or null if no error occurred.
     *
     * @return object{code:int,error:string,uid:?string,token:?string}|null Return the error object containing.
     */
    public function getError(): ?object 
    {
        return $this->error;
    }

    /**
     * Retrieve decoded JWT payload if any.
     * 
     * @param PropertyName|string|null $property Optional property name to retrieve (default: null).
     *
     * @return null|mixed|object{
     *     uid: string|int|null,
     *     sub: ?string,
     *     role: string|int|null,
     *     jti: ?string,
     *     iss: ?string,
     *     aud: ?string,
     *     iat: ?int,
     *     exp: ?int,
     *     fmv: ?string,
     *     app: ?string,
     *     ver: ?string,
     *     data: object
     * } Returns the decoded JWT payload as an object if valid.
     */
    public function getPayload(?string $property = null): ?object 
    {
        if($this->payload === null){
            return null;
        }

        return ($property === null) 
            ? $this->payload 
            : ($this->payload->{$property} ?? null);
    }

    /**
     * Get the signature sign key.
     *
     * @return OpenSSLAsymmetricKey|OpenSSLCertificate|string Return key.
     */
    public function getKey(): OpenSSLAsymmetricKey|OpenSSLCertificate|string|null
    {
        return $this->key;
    }

    /**
     * Retrieve user-based token from file if any
     *
     * @param string|int $userId The UserId.
     * @param string|null $filename Optional filename.
     * 
     * @return string
     * @throws EncryptionException If file is not readable or token not found.
     */
    public function getUserToken(string|int $userId, ?string $filename = null): string
    {
        $userId = trim((string) $userId);
        $token = self::cache($userId, 'token');

        if($token !== null){
            return $token;
        }

        self::$path ??= root('/writeable/auth/jwt/');

        $filename = ($filename === null) 
            ? self::hashFilename($userId)
            : self::parseFilename($filename);

        $file = self::$path . $filename;

        if (!is_file($file) || !is_readable($file)) {
            $this->error(
                new EncryptionException('Token file not found or not readable.'),
                throw: true
            );
            return '';
        }

        $token = @file_get_contents($file);

        if (!$token) {
            $this->error(
                new EncryptionException('File does not contain a valid token.'),
                throw: true
            );
            return '';
        }

        return self::cache($userId, 'token', $token);
    }

    /**
     * Set JWT sign key.
     *
     * @param OpenSSLAsymmetricKey|OpenSSLCertificate|string $key
     * 
     * @return self Return instance of class.
     */
    public function setKey(OpenSSLAsymmetricKey|OpenSSLCertificate|string $key): self
    {
        $this->key = $key;
        return $this;
    }

    /**
     * Generates a JWT token using HS256 (or configured algorithm).
     *
     * The payload is extended with standard JWT claims:
     * - uid: user identifier
     * - jti: token identifier (optional)
     * - role: user role (optional)
     * - iss, aud, sub: standard JWT claims
     * - iat, exp: issued-at and expiration timestamps
     * - app: app name 
     * - ver: app version
     * - fmv: Luminova version
     *
     * @param array $payload Custom claims to include in the token body.
     * @param string|int $userId Unique user identifier.
     * @param int $expiry Token lifetime in seconds (default: 3600).
     * @param string|null $tokenId Optional JWT ID (jti claim).
     * @param string|int|null $role Optional user role claim.
     *
     * @return string|null Return encoded JWT token or null if failed.
     *
     * @throws EncryptionException When token encoding fails.
     * 
     * @see self::setKey()
     * @example - Generating a JWT token.
     * 
     * ```php
     * $payload = ['name' => 'John Doe'];
     * $userId = 123;
     * $expiry = 7200; // 2 hours
     * 
     * $token = $jwt->encode($payload, $userId, $expiry, null, 'admin');
     * echo $token; // Outputs the generated JWT token
     * ```
     */
    public function encode(
        array $payload,
        string|int $userId,
        int $expiry = 3600,
        ?string $tokenId = null,
        string|int|null $role = null
    ): ?string 
    {
        $this->key ??= self::key($userId, 'sha256', $this->salt);

        try {
            $now = Time::now()->getTimestamp();
            $claims = [
                'uid'     => $userId,
                'sub'     => (string) $userId,
                'iat'     => $now,
                'exp'     => $now + $expiry,
                'iss'     => $this->iss ?? APP_URL,
                'aud'     => $this->aud ?? APP_URL . '/api',
                'fmv'     => Luminova::VERSION,
                'app'     => APP_NAME,
                'ver'     => APP_VERSION,
                'data'    => $payload,

            ];

            if ($tokenId !== null) {
                $claims['jti'] = $tokenId;
            }

            if ($role !== null) {
                $claims['role'] = $role;
            }

            $this->payload = (object) $claims;
            $this->payload->data = (object) $payload;

            return Token::encode(
                $claims,
                $this->key,
                $this->algo ?? 'HS256'
            );

        } catch (Throwable $e) {
            $this->payload = null;
            $this->error($e, $userId, throw: true);
        }

        return null;
    }

    /**
     * Decode and validate a JWT token.
     *
     * Returns a normalized object JWT payload.
     *
     * @param string $token The JWT token to decode.
     * @param string|int $userId User identifier used to derive the verification key.
     *
     * @return object{
     *     uid: string|int|null,
     *     sub: ?string,
     *     role: string|int|null,
     *     jti: ?string,
     *     iss: ?string,
     *     aud: ?string,
     *     iat: ?int,
     *     exp: ?int,
     *     fmv: ?string,
     *     app: ?string,
     *     ver: ?string,
     *     data: object
     * }|null Returns the decoded JWT payload as an object if valid.
     *
     * @throws EncryptionException When decoding fails.
     * 
     * @see self::setKey()
     * @example - Decoding JWT token.
     * 
     * ```php
     * $token = 'eyJhbGciOi...';
     * $userId = 123;
     * $decoded = $jwt->decode($token, $userId);
     * 
     * if ($decoded !== false) {
     *     // Token is valid, use $decoded->sub to access the user ID or other claims
     * } else {
     *     // Token is invalid or expired
     * }
     * ```
     */
    public function decode(string $token, string|int $userId): ?object
    {
        $this->key ??= self::key($userId, 'sha256', $this->salt);

        try {
            return $this->map(Token::decode(
                $token,
                new Key($this->key, $this->algo ?? 'HS256')
            ));
        } catch (Throwable $e) {
            $this->error($e, $userId, $token, true);
        }

        return null;
    }

    /**
     * Validate JWT token and user identity.
     *
     * This validates JWT token from file or specified token.
     * 
     * Token may include scheme prefix (e.g. "Bearer xxx").
     * Optional callback allows post-validation decision making.
     *
     * @param string|null $token Token string or null to read from file (optionally "Bearer xxx").
     * @param string|int $userId Expected user identifier.
     * @param (Closure(bool $status, object $res): bool)|null $callback Optional response callback.
     *      `function(bool $valid, object $payload|object $error): bool`
     *
     * @return bool Returns true if the authentication is valid, otherwise false.
     *          If callback is provided, return result of callback function.
     * 
     * @throws EncryptionException If an error is encountered while decoding.
     * 
     * @see self::isError()
     * @see self::getError()
     * @see self::setKey()
     * 
     * @example - Without callback:
     * ```php
     * $isValid = $jwt->validate('Bearer my-token', 'user-id');
     * if ($isValid) {
     *     // Authentication is valid
     * } else {
     *     // Authentication failed
     * }
     * ```
     *
     * @example - With a callback:
     * ```php
     * $isValid = $jwt->validate('Bearer my-token', 'user-id', function(bool $status, object $res): bool {
     *     if (!$status) {
     *         echo $res->error;
     *         return false; // Reject the token if initial validation fails
     *     }
     *     
     *     // Additional validation based on payload
     *     if ($res->maxQuota > 0) {
     *         $quota = (new User())->find($res->uid, ['usage_quota']);
     *         return $quota ? $res->maxQuota < $quota->usage_quota : false;
     *     }
     * 
     *     return true; // Accept the token if the custom validation passes
     * });
     * 
     * if ($isValid) {
     *     // Authentication is valid
     * } else {
     *     // Authentication failed
     * }
     * ```
     */
    public function validate(?string $token, string|int $userId, ?Closure $callback = null): bool 
    {
        $this->error = null;
        $this->payload = null;

        self::assertUserId($userId);

        $userId = trim((string) $userId);
        $token ??= $this->getUserToken($userId);
        $token = trim($token);

        if ($token && preg_match('/^\s*\S+\s+(\S+)/', $token, $m)) {
            $token = $m[1];
        }

        if ($token === '') {
            return $this->fail('Invalid authentication token.', $userId, $token, $callback);
        }

        try {
            $decoded = $this->decode($token, $userId);
            $this->payload = null;

            if (!($decoded instanceof stdClass)) {
                return $this->fail('Invalid token structure.', $userId, $token, $callback);
            }

            if (($decoded->uid ?? null) !== (string) $userId) {
                return $this->fail('User mismatch.', $userId, $token, $callback);
            }

            $this->payload = $decoded;

            if ($callback) {
                return (bool) $callback(true, $decoded);
            }

            return true;
        } catch (Throwable $e) {
            $this->error($e, $userId, $token, true);
        }
            
        return $callback 
            ? (bool) $callback(false, $this->error) 
            : false;
    }

    /**
     * Validate JWT token stored in user file.
     *
     * @param string|int $userId User identifier.
     * @param string|null $filename Optional file name (e.g, `user1.key`).
     * @param (Closure(bool $status, object $res): bool)|null $callback Optional response callback.
     *      `function(bool $valid, object $payload|object $error): bool`
     *
     * @return bool Returns true if the authentication is valid, otherwise false.
     *          If callback is provided, return result of callback function.
     * 
     * @throws EncryptionException On development if an error is encountered while decoding.
     * 
     * @see self::setKey()
     * @example - Example without callback:
     * ```php
     * $isValid = $jwt->validateFromFile('user-id');
     * 
     * if ($isValid) {
     *     // Authentication is valid
     * } else {
     *     // Authentication failed
     * }
     * ```
     *
     * @example - Example with callback:
     * ```php
     * $isValid = $jwt->validateFromFile('user-id', callback: function(bool $status, object $res): bool {
     *     if (!$status) {
     *         echo $res->error;
     *         return false; // Reject the token if validation fails
     *     }
     *     
     *     // Perform additional checks on the payload
     *     return isset($res->role) && $res->role === 'admin';
     * });
     * 
     * if ($isValid) {
     *     // Authentication is valid
     * } else {
     *     // Authentication failed
     * }
     * ```
     */
    public function validateFromFile(
        string|int $userId, 
        ?string $filename = null, 
        ?Closure $callback = null
    ): bool 
    {
        $this->error = null;
        $this->payload = null;

        self::assertUserId($userId);
        $userId = trim((string) $userId);

        return $this->validate(
            $this->getUserToken($userId, $filename), 
            $userId, 
            $callback
        );
    }

    /**
     * Generate a hashed filename for storing a user's private token on the server.
     * 
     * This always hash the filename using the XXH3 algorithm.
     *
     * @param string|int $userId The user identifier used to generate the filename.
     *
     * @return string Return the generated filename, either hashed or plain, with a `.key` extension.
     * @throws EncryptionException If the `$userId` is empty or invalid.
     *
     * @example - Hashed filename:
     * ```php
     * $filename = JWT::hashFilename('user-id');
     * // Example output: 'c4ca4238a0b923820dcc509a6f75849b.key'
     * ```
     */
    public static function hashFilename(string|int $userId): string
    {
        $userId = trim((string) $userId);
        $file = self::cache($userId, 'file');

        if($file !== null){
            return $file;
        }

        self::assertUserId($userId);

        $hash = Luminova::hash(
            'xxh3', 
            "{$userId}-private-jwt-key-file", 
            fallbackAlgo: 'md5'
        );

        return self::cache(
            $userId,
            'file',
            "{$hash}.key"
        );
    }

    /**
     * Generate a securely hashed encryption key.
     * 
     * This method generates a secure hashed encryption key 
     * using the user identifier and the specified hashing algorithm.
     *
     * @param string|int $userId The user identifier to generate the encryption key.
     * @param string $algo The hashing algorithm to use for the encryption key (default: `sha256`).
     * @param string|null $salt Optional salt value used as a prefix for keys (default: null).
     *
     * @return string Return the securely hashed encryption key.
     * @throws EncryptionException If the user identifier is empty or the application key is missing.
     *
     * @example - With default algorithm (sha256):
     * ```php
     * $key = JWT::key('user-id');
     * // Example output: 'b3d14f6e5576c4d8c825489f3b8b21076c85a1695d9ecf264228db24919fc699'
     * ```
     *
     * @example - With a custom hashing algorithm (e.g., md5):
     * ```php
     * $key = JWT::key('user-id', 'md5');
     * // Example output: 'f623b2b3d1e9c139aadad62f0c5d4a4327b20e63'
     * ```
     */
    public static function key(string|int $userId, string $algo = 'sha256', ?string $salt = null): string
    {
        self::assertUserId($userId);

        $userId = trim((string) $userId);
        $key = env('app.key', null);
        $salt ??= '';

        if(!$key){
            throw new EncryptionException(
                'Application key is missing. Please add "app.key=your-key" to the environment file or run "php novakit generate:key" to generate one.',
                ErrorCode::INVALID_AUTHORIZATION_SPECIFICATION
            );
        }

        return hash_hmac($algo, (string) $userId, "{$salt}{$key}");
    }

    /**
     * Generate a JWT token based on user ID, sign it, and store the private key on the server.
     *
     * @param array $payload The additional data to include in the JWT payload.
     * @param string|int $userId The unique identifier for the user.
     * @param int $expiry The expiration time in seconds (default is 30 days or 2592000 seconds).
     * @param string|null $filename Optional file name (e.g, `user1.key`).
     *
     * @return string|null Returns hash filepath if the token was successfully signed and stored, otherwise `null`.
     *
     * @see self::setKey()
     * @example - Signing a JWT token for a user:
     * ```php
     * $payload = ['role' => 'admin', 'maxQuota' => 100];
     * $userId = 'user123';
     * 
     * $filepath = $jwt->sign($payload, $userId, 3600);
     * 
     * if ($filepath !== null ) {
     *     echo "Token signed and stored. File hash: $filepath";
     * } else {
     *     echo "Failed to sign or store the token.";
     * }
     * ```
     */
	public function sign(
        array $payload, 
        string|int $userId, 
        int $expiry = 2592000,
        ?string $filename = null
    ): ?string
    {
        $token = $this->encode(
            $payload, 
            $userId, 
            $expiry
        );

        if(!$token){
            return null;
        }

        $filename = ($filename === null) 
            ? self::hashFilename($userId)
            : self::parseFilename($filename);
        
        try{
            if($this->store($filename, $token)){
                return self::$path . $filename;
            }
        } catch(Throwable){}

        return null;
    }

    /**
     * Store the user private key on the server.
     *
     * @param string $filename The name of the file to store the key in.
     * @param string $token The authentication token string to store (e.g., JWT token).
     *
     * @return bool Returns `true` if the key was successfully saved, `false` otherwise.
     * @throws EncryptionException If an error occurs while saving the key.
     *
     * @example - Encoding a JWT token and storing it in a file:
     * 
     * ```php
     * $payload = ['role' => 'admin', 'maxQuota' => 100];
     * $userId = 'user123';
     * $expiry = 3600; // 1 hour
     * 
     * // Generate JWT token using encode method
     * $token = $jwt->encode($payload, $userId, $expiry);
     * 
     * // Store the token using store method
     * $filename = JWT::hashFilename($userId);
     * $isStored = $jwt->store($filename, $token);
     * 
     * if ($isStored) {
     *     echo "Token stored successfully in file: $filename";
     * } else {
     *     echo "Failed to store the token.";
     * }
     * ```
     */
    public function store(string $filename, string $token): bool
    {
        if($token === ''){
            return false;
        }

        $filename = self::parseFilename($filename);

		try{
			self::$path ??= root('/writeable/auth/jwt/');

			if(!make_dir(self::$path)){
                return false;
            }

            $file = self::$path . $filename;

			if (is_file($file)) {
				@unlink($file);
			}

            if(!is_writable($file)){
                throw new EncryptionException(
                    sprintf(
                        'The provided path "%s" is not writable for file: "%s".',
                        self::$path,
                        $filename
                    ),
                    ErrorCode::INVALID_ARGUMENTS
                );
            }

			return write_content($file, $token);
		}catch(Throwable $e){
            $this->error($e, throw: true);
		}

        return false;
    }

    /**
     * Cache token and file hashes
     *
     * @param string $key JWT user ID
     * @param string $context (`file` or `token`)
     * @param mixed $value Optional value to set.
     * 
     * @return mixed Return value.
     */
    private static function cache(string $key, string $context, mixed $value = null): mixed 
    {
        if($value === null){
            if(isset(self::$cache[$key][$context])){
                return self::$cache[$key][$context];
            }

            return null;
        }

        $user = self::$cache[$key] ?? [];
        $user[$context] = $value;

        self::$cache[$key] = $user;

        return $value;
    }

    /**
     * Map decoded payload for consistency.
     *
     * @param object $decoded
     * 
     * @return object
     */
    private function map(object $decoded): object 
    {
        $data = $decoded->data ?? (object)[];
        $version = $decoded->ver ?? null;

        if(isset($decoded->version)){
            $data = $this->mapLegacyData($decoded);
            $version = $decoded->version ?? null;
        }

        return $this->payload = (object) [
            'uid'  => $decoded->uid ?? null,
            'sub'  => $decoded->sub ?? null,
            'role' => $decoded->role ?? null,
            'jti'  => $decoded->jti ?? null,
            'iss'  => $decoded->iss ?? null,
            'aud'  => $decoded->aud ?? null,
            'iat'  => $decoded->iat ?? null,
            'exp'  => $decoded->exp ?? null,
            'fmv'  => $decoded->fmv ?? null,
            'app'  => $decoded->app ?? null,
            'ver'  => $version,
            'data' => $data,
        ];
    }

    /**
     * Map legacy payload.
     *
     * @param object $decoded
     * 
     * @return object Return extracted data from payload
     */
    private function mapLegacyData(object $decoded): object 
    {
        static $skip = null;

        $data = [];
        $skip ??= [
            'uid'     => true,
            'sub'     => true,
            'iat'     => true,
            'exp'     => true,
            'iss'     => true,
            'aud'     => true,
            'fmv'     => true,
            'app'     => true,
            'ver'     => true,
            'version' => true,
            'role'    => true,

        ];

        foreach($decoded as $key => $value){
            if(!isset($skip[$key])){
                $data[$key] = $value;
            }
        }

        return (object) $data;
    }

    /**
     * Handles and logs errors during JWT validation.
     *
     * @param Throwable $e The exception or error that occurred during validation.
     * @param string|int|null $userId The user ID associated with the token.
     * @param string|null $token The JWT token being validated.
     *
     * @return stdClass Return an object containing error details.
     * @throws EncryptionException
     */
    private function error(
        Throwable $e,
        string|int|null $userId = null, 
        ?string $token = null,
        bool $throw = false
    ): object 
    {
        $err = $e->getPrevious() ?? $e;
        $this->fail(
            $err->getMessage(),
            $userId,
            $token,
            null,
            $err->getCode()
        );

        if(!$throw && PRODUCTION){
            Logger::dispatch('emergency', 'JWT validate error: ' . $err->getMessage(), [
                'userId' => $userId
            ]);
        }

        if(!$throw){
            return $this->error;
        }

        if(!$e instanceof LuminovaException){
            $e = (new EncryptionException($err->getMessage(), $err->getCode(), $err))
                ->setFile($err->getFile())
                ->setLine($err->getLine());
        }

        throw $e;
    }

    /**
     * Set the authentication token filename.
     *
     * Only the filename portion is stored. Any directory path in the input is
     * removed using `basename()`.
     *
     * @param string $filename The filename or file path.
     *
     * @return string Returns normalized filename.
     *
     * @throws InvalidArgumentException If the filename is empty or invalid.
     */
    private static function parseFilename(string $filename): string
    {
        $filename = trim($filename);
        $file = basename($filename);

        if ($file === '' || $file === '.' || $file === '..') {
            throw new InvalidArgumentException(sprintf(
                'Invalid filename "%s". A valid filename is required.',
                $filename
            ));
        }

        return $file;
    }

    /**
     * Assert user ID
     *
     * @param string|int $userId
     * 
     * @return void
     * @throws InvalidArgumentException
     */
    private static function assertUserId(string|int $userId): void
    {
        $userId = trim((string) $userId);

        if ($userId === '') {
            throw new InvalidArgumentException(
                'User ID must not be any empty string.',
                ErrorCode::INVALID_ARGUMENTS
            );
        }
    }

    /**
     * Failure handler
     *
     * @param string $message
     * @param string|int $userId
     * @param string|null $token
     * @param Closure|null $callback
     * @param int $code
     * 
     * @return bool
     */
    private function fail(
        string $message, 
        string|int $userId, 
        ?string $token = null, 
        ?Closure $callback = null,
        int $code = 0,
    ): bool 
    {
        $this->error = (object) [
            'code'   => $code,
            'error'  => $message,
            'uid'    => $userId,
            'token'  => $token
        ];

        return $callback 
            ? (bool) $callback(false, $this->error) 
            : false;
    }

    /**
     * Hash user filename.
     *
     * @param string|integer $userId
     * @return string Return hashed user filename.
     * 
     * @deprecated Use hashFilename()
     */
    public static function filename(string|int $userId, bool $hashValue = true): string
    {
        $userId = trim((string) $userId);
        $file = self::cache($userId, 'file');

        if($file !== null){
            return $file;
        }

        self::assertUserId($userId);

        $filename = "{$userId}-private-jwt-key-file";
        $hash = $hashValue ? md5($filename) : $filename;

        return self::cache(
            $userId, 
            'file',
            "{$hash}.key"
        );
    }
}