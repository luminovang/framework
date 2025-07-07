<?php 
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

use \Luminova\Luminova;
use \Luminova\Time\Time;
use \Luminova\Logger\Logger;
use \Luminova\Interface\LazyInterface;
use \Firebase\JWT\{JWT, Key};
use \Luminova\Exceptions\{AppException, EncryptionException};
use \Closure;
use \stdClass;
use \Throwable;
use function \Luminova\Funcs\{
    root,
    write_content,
    get_content,
    make_dir
};

class JWTAuth implements LazyInterface
{
    /**
     * Shared instance of the JWTAuth.
     * 
     * @var self|null $instance
     */
	private static ?self $instance = null;

    /**
     * Validation error.
     * 
     * @var object|null $error
     */
    private ?object $error = null;

	/**
     * Initialize the JWTAuth class constructor with configurable JWT and key settings.
     *
     * @param string|null $algo The algorithm used for JWT encoding and decoding (default: 'HS256').
     * @param string|null $salt Optional salt value used as a prefix for keys (default: null).
     * @param string|null $path The directory path for storing generated keys (default: '/writeable/auth/').
     * @param string|null $iss The issuer claim for the JWT, typically the application URL (default: APP_URL).
     * @param string|null $aud The audience claim for the JWT, typically the API endpoint (default: APP_URL . '/api').
     * 
     * @throws EncryptionException If the provided $path is not readable or writable.
     * @link https://luminova.ng/docs/0.0.0/security/jwt
     */
    public function __construct(
        protected ?string $algo = null,
        protected ?string $salt = null,
        protected ?string $path = null,
        protected ?string $iss = null, 
        protected ?string $aud = null
    )
	{
        $this->error = null;
        if($this->path !== null && (!is_writable($this->path) || !is_readable($this->path))){
            throw new EncryptionException(
                sprintf(
                    'The provided path "%s" must be readable and writable.',
                    $this->path
                ),
                EncryptionException::INVALID_ARGUMENTS
            );
        }
	}

    /**
     * Get or create a singleton instance of the JWTAuth class.
     *
     * @param string|null $algo The algorithm used for JWT encoding and decoding (default: HS256).
     * @param string|null $salt Optional salt value used as a prefix for keys (default: null).
     * @param string|null $path The directory path for storing generated keys (default: '/writeable/auth/').
     * @param string|null $iss The issuer claim for the JWT, typically the application URL (default: APP_URL).
     * @param string|null $aud The audience claim for the JWT, typically the API endpoint (default: APP_URL . '/api').
     *
     * @return self Returns the singleton instance of the JWTAuth class.
     * @throws EncryptionException If the provided $path is not readable or writable.
     * @link https://luminova.ng/docs/0.0.0/security/jwt
     */
    public static function getInstance(
        ?string $algo = null,
        ?string $salt = null,
        ?string $path = null,
        ?string $iss = null, 
        ?string $aud = null
    ): self 
    {
        if(!self::$instance instanceof self){
            self::$instance = new self($algo, $salt, $path, $iss, $aud);
        }

        return self::$instance;
    }

    /**
     * Retrieves the validation error object.
     *
     * @return object|null Return the error object containing details about the last validation error,
     *                     or null if no error occurred or no validation has been performed.
     */
    public function getError(): ?object 
    {
        return $this->error;
    }

	/**
     * Generates a JWT token using the `HS256` algorithm.
     *
     * @param array $payload The payload for the JWT, containing any custom claims to be included in the token.
     * @param string|int $userId The identifier for the user. This is typically a unique ID associated with the user.
     * @param int $expiry The expiration time in seconds (default: 3600).
     * @param string|null $tokenId JWT ID: a unique identifier for this token (optional).
     * @param string|int|null $role Custom claim: the role of the user (optional).
     *
     * @return string Return the generated JWT token.
     *
     * @example - Generating a JWT token.
     * 
     * ```php
     * $payload = ['name' => 'John Doe'];
     * $userId = 123;
     * $expiry = 7200; // 2 hours
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
    ): string
    {
        $iat = Time::now()->getTimestamp();

        if($tokenId !== null){
            $payload['jti'] = $tokenId;
        }

        if($role !== null){
            $payload['role'] = $role;
        }

        $payload['uid'] = $userId;
        $payload['fmv'] = Luminova::VERSION;
        $payload['app'] = APP_NAME;
        $payload['version'] = APP_VERSION;
        
        return JWT::encode(
            array_merge($payload, [
                'iss' => $this->iss ?? APP_URL,
                'aud' => $this->aud ?? APP_URL . '/api',
                'sub' => (string) $userId,
                'iat' => $iat,
                'exp' => $iat + $expiry
            ]), 
            self::key($userId, 'sha256'), $this->algo ?? 'HS256'
        );
    }

    /**
     * Decode and validate a JWT token.
     *
     * @param string $token The JWT token to decode.
     * @param string|int $userId The user identifier, used to generate the key for decoding.
     *
     * @return stdClass Returns the decoded JWT payload as an object if valid.
     * @throws EncryptionException Throws on development mode if an error is encountered while decoding.
     *
     * @example - Decoding JWT token.
     * 
     * ```php
     * $token = 'eyJhbGciOi...';
     * $userId = 123;
     * $decoded = $jwt->decode($token, $userId);
     * if ($decoded !== false) {
     *     // Token is valid, use $decoded->sub to access the user ID or other claims
     * } else {
     *     // Token is invalid or expired
     * }
     * ```
     */
    public function decode(string $token, string|int $userId): stdClass
    {
        try {
            return JWT::decode(
                $token, 
                new Key(self::key($userId, 'sha256'), $this->algo ?? 'HS256')
            );
        } catch (Throwable $e) {
            throw new EncryptionException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Validate token and user ID, with an optional callback for additional validation on the payload.
     * 
     * @param string|null $token The authentication token, typically in the format `scheme token` (e.g., `Bearer my-token`).
     * @param string|int $userId The user identifier, which should match the `userId` claim in the token.
     * @param Closure|null $callback An optional callback function that is invoked with the validation result and the decoded payload.
     *                              The callback can be used to perform additional checks, such as user-specific data validation.
     *
     * @return bool Returns `true` if the authentication is valid, otherwise `false`.
     * @throws EncryptionException Throws on development mode if an error is encountered while decoding.
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
     * $isValid = $jwt->validate('Bearer my-token', 'user-id', function(bool $passed, \stdClass $payload): bool {
     *     if (!$passed) {
     *         echo $payload->err->uid;
     *         return false; // Reject the token if initial validation fails
     *     }
     *     
     *     // Additional validation based on payload
     *     if ($payload->maxQuota > 0) {
     *         $quota = (new User())->find($payload->uid, ['usage_quota']);
     *         return $quota ? $payload->maxQuota < $quota->usage_quota : false;
     *     }
     *     return true; // Accept the token if the custom validation passes
     * });
     * if ($isValid) {
     *     // Authentication is valid
     * } else {
     *     // Authentication failed
     * }
     * ```
     */
    public function validate(
        ?string $token, 
        string|int $userId,
        ?Closure $callback = null
    ): bool
    {
        $valid = false;
        $decoded = new stdClass();
        $decoded->err = true;
        $this->error = null;

        if($token && $userId){
            // Match any scheme (e.g., Bearer, Token, etc.)
            // The first part is the scheme (Bearer, Token, etc.), the second part is the token
            if (preg_match('/^\s*(\S+)\s+(\S+)/', $token, $matches)) {
                $token = $matches[2];
            }

            try{
                $decoded = $this->decode($token, $userId);

                if($decoded instanceof stdClass){
                    $valid = (!empty($decoded->uid) && (string) $userId === $decoded->uid);
                    $decoded->err = $valid ? null : true;
                }
            }catch(EncryptionException $e){
                $decoded = $this->error($e, $userId, $token);
            }
        }

        if($decoded->err === true){
            $this->error = $decoded->err = (object)[
                'code' => 0,
                'message' => 'Invalid authentication token or client ID.',
                'uid' => $userId,
                'token'   => $token
            ];
        }

        return ($callback instanceof Closure) ? $callback($valid, $decoded) : $valid;
    }

	/**
     * Validate the authentication key from a file stored in a specified path.
     * 
     * @param string|int $userId The user identifier whose authentication token is stored in a file.
     * @param Closure|null $callback An optional callback function that can be used to perform additional validation on the payload.
     *                              The callback will receive the validation result and the decoded payload.
     *
     * @return bool Returns `true` if the authentication is valid and the token was successfully validated, otherwise `false`.
     * @throws EncryptionException Throws on development mode if an error is encountered while decoding.
     * 
     * @example - Example without callback:
     * ```php
     * $isValid = $jwt->validateFromFile('user-id');
     * if ($isValid) {
     *     // Authentication is valid
     * } else {
     *     // Authentication failed
     * }
     * ```
     *
     * @example - Example with callback:
     * ```php
     * $isValid = $jwt->validateFromFile('user-id', function(bool $valid, \stdClass $payload): bool {
     *     if (!$valid) {
     *         echo $payload->err->message;
     *         return false; // Reject the token if validation fails
     *     }
     *     
     *     // Perform additional checks on the payload
     *     return isset($payload->role) && $payload->role === 'admin';
     * });
     * if ($isValid) {
     *     // Authentication is valid
     * } else {
     *     // Authentication failed
     * }
     * ```
     */
    public function validateFromFile(string|int $userId, ?Closure $callback = null): bool
    {
        $this->error = null;
        
        if($userId){
            $this->path ??= root('/writeable/auth/');
            $file = $this->path . self::filename($userId);

            if (file_exists($file) && ($token = get_content($file)) !== false) {
                return $this->validate($token, $userId, $callback);
            }
        }

        if($callback instanceof Closure){
            $err = new stdClass();
            $this->error = $err->err = (object)[
                'code' => 0,
                'message' => 'Invalid authentication token or client ID.',
                'uid' => $userId,
                'token'   => null
            ];

            return $callback(false, $err);
        }

        return false;
    }

    /**
     * Generate a hashed or plain filename for storing a user's private key on the server.
     *
     * @param string|int $userId The user identifier used to generate the filename.
     * @param bool $md5Hash Whether to hash the filename using the MD5 algorithm (default: `true`).
     *
     * @return string Return the generated filename, either hashed or plain, with a `.key` extension.
     * @throws EncryptionException If the `$userId` is empty or invalid.
     *
     * @example - With hashed filename:
     * ```php
     * $filename = JWTAuth::filename('user-id');
     * // Example output: 'c4ca4238a0b923820dcc509a6f75849b.key'
     * ```
     *
     * @example - With plain filename:
     * ```php
     * $filename = JWTAuth::filename('user-id', false);
     * // Example output: 'user-id-private-jwt-key-file.key'
     * ```
     */
    public static function filename(string|int $userId, bool $md5Hash = true): string
    {
        if (!$userId) {
            throw new EncryptionException(
                'Unable to create key path, $userId reference must not be empty',
                EncryptionException::INVALID_ARGUMENTS
            );
        }

        $filename = "{$userId}-private-jwt-key-file";
        return ($md5Hash ? md5($filename) : $filename) . ".key";
    }

    /**
     * Generate a securely hashed encryption key using the user identifier and the specified hashing algorithm.
     *
     * @param string|int $userId The user identifier to generate the encryption key.
     * @param string $algo The hashing algorithm to use for the encryption key (default: `sha256`).
     * @param string|null $salt Optional key salt prefix (default: `NULL`).
     *
     * @return string Return the securely hashed encryption key.
     * @throws EncryptionException If the user identifier is empty or the application key is missing.
     *
     * @example - With default algorithm (sha256):
     * ```php
     * $key = $jwt->key('user-id');
     * // Example output: 'b3d14f6e5576c4d8c825489f3b8b21076c85a1695d9ecf264228db24919fc699'
     * ```
     *
     * @example - With a custom hashing algorithm (e.g., md5):
     * ```php
     * $key = $jwt->key('user-id', 'md5');
     * // Example output: 'f623b2b3d1e9c139aadad62f0c5d4a4327b20e63'
     * ```
     */
    public function key(string|int $userId, string $algo = 'sha256'): string
    {
        if (!$userId) {
            throw new EncryptionException(
                'Unable to create encryption key: user identifier must not be empty.',
                EncryptionException::INVALID_ARGUMENTS
            );
        }

        $key = env('app.key', null);

        if(!$key){
            throw new EncryptionException(
                'Application key is missing. Please add "app.key=your-key" to the environment file or run "php novakit generate:key" to generate one.',
                EncryptionException::INVALID_AUTHORIZATION_SPECIFICATION
            );
        }

        return hash_hmac($algo, (string) $userId, ($this->salt ?? '') . $key);
    }

    /**
     * Generate a JWT token based on user ID, sign it, and store the private key on the server.
     *
     * @param array $payload The additional data to include in the JWT payload.
     * @param string|int $userId The unique identifier for the user.
     * @param int $expiry The expiration time in seconds (default is 30 days or 2592000 seconds).
     * @param string|null &$fileHash A reference to a variable that will hold the key file hash, used to identify the stored key file.
     *
     * @return bool Returns `true` if the token was successfully signed and stored, otherwise `false`.
     *
     * @example - Signing a JWT token for a user:
     * ```php
     * $payload = ['role' => 'admin', 'maxQuota' => 100];
     * $userId = 'user123';
     * $fileHash = null;
     * $result = $jwt->sign($payload, $userId, 3600, $fileHash);
     * if ($result) {
     *     echo "Token signed and stored. File hash: $fileHash";
     * } else {
     *     echo "Failed to sign or store the token.";
     * }
     * ```
     */
	public function sign(
        array $payload, 
        string|int $userId, 
        int $expiry = 2592000,
        ?string &$fileHash = null
    ): bool
    {
        $filename = self::filename($userId);
        $hash = rtrim($filename, '.key');
        $auth = $this->encode(
            $payload, 
            $userId, 
            $expiry, 
            $hash
        );

        if(!$auth){
            return false;
        }
        
		if($this->store($filename, $auth)){
            $fileHash = $hash;
            return true;
        }

        return false;
    }

    /**
     * Store the user private key on the server.
     *
     * @param string $filename The name of the file to store the key in.
     * @param string $key The authentication key value (e.g., JWT token) to store.
     *
     * @return bool Returns `true` if the key was successfully saved, `false` otherwise.
     * @throws EncryptionException Throws on development mode if an error occurs while saving the key.
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
     * $filename = JWTAuth::filename($userId);
     * $isStored = $jwt->store($filename, $token);
     * 
     * if ($isStored) {
     *     echo "Token stored successfully in file: $filename";
     * } else {
     *     echo "Failed to store the token.";
     * }
     * ```
     */
    public function store(string $filename, string $key): bool
    {
		try{
			$this->path ??= root('/writeable/auth/');
			if(!make_dir($this->path)){
                return false;
            }

			if (file_exists($this->path . $filename)) {
				@unlink($this->path . $filename);
			}

			return write_content($this->path . $filename, $key);
		}catch(AppException|Throwable $e){
			if(PRODUCTION){
                Logger::dispatch('emergency', $e->getMessage());
                return false;
            }

            throw new EncryptionException($e->getMessage(), $e->getCode(), $e);
		}
    }

    /**
     * Handles and logs errors during JWT validation.
     *
     * @param Throwable $e The exception or error that occurred during validation.
     * @param string|int|null $userId The user ID associated with the token.
     * @param string|null $token The JWT token being validated.
     *
     * @return stdClass Return an object containing error details.
     */
    private function error(
        Throwable $e,
        string|int|null $userId = null, 
        ?string $token = null
    ): stdClass 
    {
        $e = ($e->getPrevious() === null) ? $e : $e->getPrevious();

        if(PRODUCTION){
            Logger::dispatch('emergency', 'JWT validate error: ' . $e->getMessage(), [
                'userId' => $userId
            ]);
        }

        $err = new stdClass();
        $this->error = $err->err = (object)[
            'code' => $e->getCode(),
            'message' => PRODUCTION ? 'Authentication failed, invalid token or client ID.' : $e->getMessage(),
            'uid' => $userId,
            'token' => $token
        ];

        return $err;
    }
}