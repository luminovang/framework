<?php
/**
 * Luminova Framework Password Manager
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Security;

use \Throwable;
use \Luminova\Http\Client\Novio;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Exceptions\InvalidArgumentException;
use function \Luminova\Funcs\root;

/**
 * Password Documentation.
 * 
 * @link https://luminova.ng/docs/0.0.0/security/password
 */
final class Password 
{
	/**
	 * Temporary store pwned hash suffix to reduce network request.
	 * 
	 * @var array<string,bool> $memory
	 */
	private static array $memory = [];

	/**
	 * Common insecure passwords to prevent weak password usage.
	 *
	 * This list can be expanded as needed. Passwords are in lowercase for easy comparison.
	 * 
	 * @param array $insecure
	 */
	private static array $insecure = [
		'123456', '123456789', 'password', '12345678', 'qwerty', 'p@ssw0rd',
		'12345', '1234567', '111111', '123123', 'abc123', '12345@123', '@dmin',
		'password1', '1234', 'iloveyou', '1q2w3e4r', 'admin', 'admin@123',
		'welcome', 'monkey', 'login', 'letmein', 'princess', '12345@12345',
		'solo', 'passw0rd', 'starwars', 'dragon', 'sunshine', 'mypassword',
		'master', 'hello', 'freedom', 'whatever', 'qazwsx', 'admin123', 'test123',
		'guest', 'access', 'secret', '654321', 'asdfgh', 'qwert',
		'1q2w3e', 'football', 'welcome1', 'changeit', 'testtest', 'default',
	];

	/**
	 * Generate a secure password hash using the specified or default algorithm.
	 *
	 * This method automatically selects the most secure available algorithm
	 * (Argon2id, Argon2i, or BCRYPT) unless overridden in `$options` using 
	 * the keys `algorithm` or `algo`.
	 *
	 * The hashing process uses PHP's built-in `password_hash()` function, 
	 * ensuring compatibility with `password_verify()` and `password_needs_rehash()`.
	 *
	 * @param string $password Plain text password to hash.
	 * @param array|null $options Optional settings:
	 *     - **algorithm|algo**: One of PASSWORD_DEFAULT, PASSWORD_BCRYPT, PASSWORD_ARGON2I, or PASSWORD_ARGON2ID.  
	 *     - Any algorithm-specific options (e.g., `cost`, `memory_cost`, `time_cost`).
	 *
	 * @return string Returns the hashed password string.
	 * @throws InvalidArgumentException If the password is empty, 
	 * 		if contains invalid characters leading and trailing spaces,
	 *   	if an unsupported algorithm is specified.
	 * 
	 * @see verify() To verify password hash with optional rehash callback.
	 * @see isValid() To verify password hash
	 *
	 * @example - Examples:
	 * ```php
	 * // Default hashing (uses the most secure available algorithm)
	 * $hash = Password::hash('StrongPass123!');
	 *
	 * // Specify algorithm and cost
	 * $hash = Password::hash('StrongPass123!', [
	 *     'algorithm' => PASSWORD_BCRYPT,
	 *     'cost' => 12
	 * ]);
	 * ```
	 */
	public static function hash(string $password, ?array $options = null): string
	{
		self::assert($password);
		$options ??= [];

		return password_hash(
			$password, 
			self::algorithm($options),
			$options
		);
	}

    /**
     * Generate a random secure numeric PIN and its hashed value.
     *
     * @param int $length The length of the PIN to generate (Default: `6 digits`).
     * @param string|null $label Optional label (contextual identifier, e.g. user ID or card ID).
     * @param string|null $algo The hashing algorithm e.g. 'sha256', 'sha512' (default: 'sha256').
     *
     * @return array{0:string,1:string,2:string} Returns an array with:
     *  - [0] => raw PIN.
     *  - [1] => hashed PIN.
     *  - [2] => label used.
	 * @throws RuntimeException If the application key is missing (`env('app.key')`).
	 * @see isPin() - To verify generated PIN.
     *
     * @example - Example:
     * ```php
     * [$pin, $hash, $label] = Password::pin(6, 'VOTE-1234');
	 * 
     * echo "PIN: {$pin}, HASH: {$hash}";
     * // => PIN: 482193, HASH: 4bfa2e... (depends on app.key)
     * ```
     */
    public static function pin(
        int $length = 6, 
        ?string $label = null,
        ?string $algo = null
    ): array 
	{
        $length = max(4, $length);
        $pin = '';

        for ($i = 0; $i < $length; $i++) {
            $pin .= random_int(0, 9);
        }

        $label ??= '';
        return [
            $pin,
            self::hashPin($pin, $label, $algo),
            $label
        ];
    }

    /**
     * Verify if a provided PIN matches its hashed version.
     *
     * @param string $pin The plain numeric PIN.
     * @param string $hash The stored hashed PIN to verify against.
     * @param string|null $label The label used during hashing.
     * @param string|null $algo The hashing algorithm used.
     *
     * @return bool Returns true if the PIN is valid, false otherwise.
	 * @throws RuntimeException If the application key is missing (`env('app.key')`).
	 * @see pin() - To generate a new PIN.
     *
     * @example - Example:
     * ```php
     * [$pin, $hash] = Password::pin(6, 'VOTE-1234');
	 * 
     * if (Password::isPin($pin, $hash, 'VOTE-1234')) {
     *     echo "PIN verified!";
     * } else {
     *     echo "Invalid PIN.";
     * }
     * ```
     */
    public static function isPin(
        string $pin, 
        string $hash,
        ?string $label = null, 
        ?string $algo = null
    ): bool 
	{
		if(!$pin || !$hash){
			return false;
		}

        return hash_equals(
            $hash, 
            self::hashPin($pin, $label, $algo)
        );
    }

	/**
	 * Verify a password against its stored hash and rehash if needed.
	 * 
	 * If the password matches and `$onNeedRehash` is provided, this method will
	 * check whether the hash should be rehashed with the current options. If so,
	 * the callback is invoked with the new hash so it can be stored.
	 *
	 * @param string $password The plain-text password to verify.
	 * @param string $hash The stored password hash to verify against.
	 * @param (callable(string $newHash): void)|null $onNeedsRehash Optional callback to handle rehash.
	 * @param array|null $options Optional algorithm options for rehash (e.g, `['algorithm' => PASSWORD_BCRYPT, 'cost' => 12]`).
	 *
	 * @return bool Return true if the password is valid, false otherwise.
	 * @throws InvalidArgumentException If the algorithm provided in options is invalid.
	 * 
	 * @see rehash()
	 * @see isValid()
	 * 
	 * > **Note:**
	 * > This method verifies only password created by `Password::hash()`, 
	 * > `Password::rehash()` or `password_hash()`.
	 */
	public static function verify(
		string $password,
		string $hash,
		?callable $onNeedsRehash = null,
		?array $options = null
	): bool 
	{
		if (!self::isValid($password, $hash)) {
			return false;
		}

		$options ??= [];

		if ($onNeedsRehash && self::isRehashable($hash, $options)) {
			$onNeedsRehash(self::hash($password, $options));
		}

		return true;
	}

	/**
	 * Verify a password and return a new hash if rehashing is required.
	 *
	 * @param string $password Plain password to verify.
	 * @param string $hash Existing password hash.
	 * @param array|null $options Options for rehash.
	 * 
	 * @return string|null New hash if password is valid and rehashing needed, otherwise null.
	 */
	public static function rehash(string $password, string $hash, ?array $options = null): ?string
	{
		$options ??= [];
		return (self::verify($password, $hash) && self::isRehashable($hash, $options))
			? self::hash($password, $options)
			: null;
	}

	/**
	 * Generate a random salt.
	 *
	 * @param int $length Length of salt in bytes (default: 16).
	 * 
	 * @return string Return generated salt Base64-encoded.
	 */
	public static function salt(int $length = 16): string 
	{
		return base64_encode(random_bytes($length));
	}

	/**
	 * Check if a password hash needs rehashing using current options.
	 *
	 * @param string $hash Existing password hash.
	 * @param array|null $options Algorithm options to check against.
	 * 
	 * @return bool Return true if hash should be rehashed.
	 */
	public static function isRehashable(string $hash, ?array $options = null): bool 
	{
		$options ??= [];
		return password_needs_rehash($hash, self::algorithm($options), $options);
	}

	/**
	 * Check if a password meets strength requirements.
	 *
	 * Strength is measured by counting how many of the following categories appear:
	 * - Numbers
	 * - Uppercase letters
	 * - Lowercase letters
	 * - Special characters
	 * - Minimum length (8+ characters)
	 *
	 * @param string $password  Password to check.
	 * @param int $complexity Required number of categories (1–6) (default 4).
	 * @param int $min Minimum password length (default 8).
	 * @param int|null $max Optional maximum length. Null means no limit.
	 *
	 * @return bool Return true if the password meets both length and complexity requirements.
	 * 
	 * @see strength()
	 * 
	 * > **Note:**
	 * > This method uses `strength()` to determine password complexity.
	 */
	public static function isStrong(
		string $password, 
		int $complexity = 4, 
		int $min = 8, 
		?int $max = null
	): bool 
	{
		$password = trim($password);

		if ($password === '') {
			return false;
		}

		$length = strlen($password);

		if ($length < $min) {
			return false;
		}

		if ($max !== null && $length > $max) {
			return false;
		}

		$complexity = max(1, min(6, $complexity));

		return self::strength($password) >= $complexity;
	}

	/**
	 * Check if a password is **not** in the list of known insecure passwords.
	 * 
	 * This method validates the given password against a predefined list of weak,
	 * common, or easily guessable passwords. You can also provide your own list of
	 * insecure passwords, or merge it with the default internal list.
	 *
	 * @param string $password The password to check.
	 * @param array  $insecure Optional custom list of insecure passwords.
	 * @param bool $includeDefaults Whether to include the default insecure list (default: true).
	 * 
	 * @return bool Returns true if the password is considered secure (not found in any insecure list).
	 */
	public static function isSecure(
		string $password, 
		array $insecure = [], 
		bool $includeDefaults = true
	): bool
	{
		$password = trim($password);

		if($password === ''){
			return false;
		}

		if($insecure !== []){
			$insecure = array_map('strtolower', $insecure);
		}
		
		$password = strtolower($password);
		$insecure = $includeDefaults
			? array_unique(array_merge(self::$insecure, $insecure))
			: ($insecure ?: self::$insecure);

		return $insecure === [] || !in_array($password, $insecure, true);
	}

	/**
	 * Check if a given password matches the user's old hashed password.
	 *
	 * This method securely verifies whether the provided password is the same
	 * as the previously stored password by comparing it against the hash.
	 *
	 * @param string $password The plain-text password to verify.
	 * @param string $hash The hashed password stored in the database.
	 *
	 * @return bool Returns true if the password matches the old one, false otherwise.
	 * 
	 * @see verify() - If you need rehash callback.
	 * 
	 * > **Note:**
	 * > This method verifies only password created by `Password::hash()`, 
	 * > `Password::rehash()` or `password_hash()`.
	 */
	public static function isValid(string $password, string $hash): bool
	{
		if (!$password || !$hash) {
			return false;
		}

		return password_verify($password, $hash);
	}

	/**
	 * Calculate how many character categories a password matches.
	 *
	 * Categories checked:
	 * - Minimum length (N+ characters)
	 * - Numbers
	 * - Uppercase letters
	 * - Lowercase letters
	 * - Special characters
	 * - Not a common known password
	 *
	 * @param string $password Password to analyze.
	 * @param int $min Minimum password length (default 8).
	 * 
	 * @return int Return the number of matched categories (0–6).
	 */
	public static function strength(string $password, int $min = 8): int 
	{
		$password = trim($password);

		if ($password === '') {
			return 0;
		}

		$score = (strlen($password) >= $min) ? 1 : 0;
		$score += self::isSecure($password) ? 1 : 0;

		$patterns = [
			'/\d/',          // Contains numbers
			'/[A-Z]/',       // Contains uppercase letters
			'/[a-z]/',       // Contains lowercase letters
			'/[^a-zA-Z\d]/', // Contains special characters
		];

		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $password)) {
				$score++;
			}
		}

		return $score;
	}

	/**
	 * Generate a cryptographically secure random password.
	 * 
	 * Length must be greater than 0.
	 *
	 * @param int $length Password length (default: 8).
	 *
	 * @return string Return secure random password.
	 * @example - Example:
	 * 
	 * ```php
	 * Password::random(16); // Generates a secure password of 16 characters.
	 * ```
	 */
	public static function random(int $length = 8): string 
	{
		if ($length <= 0) {
			return '';
		}

		$characters = 'abcdefghijklmnopqrstuvwxyz'
			. 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'
			. '0123456789'
			. '%#^_-@!$&*+=|~?<>[]{}()';
		
		$maxIndex = strlen($characters) - 1;
		$password = '';

		for ($i = 0; $i < $length; $i++) {
			$password .= $characters[random_int(0, $maxIndex)];
		}

		return $password;
	}

	/**
	 * Mask a password for safe display (e.g. in logs).
	 * 
	 * This method replaces all but the last *N* characters with `*`.
	 *
	 * @param string $password Password to mask.
	 * @param int $visible The number of last characters to leave visible (minimum: 0).
	 * 
	 * @return string Returns a masked password string.
	 * 
	 * > Use this when logging or debugging sensitive data without revealing full passwords.
	 */
	public static function mask(string $password, int $visible = 3): string 
	{
		$len = strlen($password);

		if ($len === 0) {
			return '';
		}

		$visible = max(0, $visible);

		if ($visible >= $len) {
			return str_repeat('*', $len);
		}

		return str_repeat('*', $len - $visible) . substr($password, -$visible);
	}

	/**
     * Determine if a password has been exposed in public data breaches.
     *
     * Uses the "Have I Been Pwned" (HIBP) Pwned Passwords API with the 
     * k-anonymity model: only the first 5 characters of the password's 
     * SHA-1 hash are sent to the API, ensuring the full password is never
     * exposed over the network.
     *
     * @param string $password Plain text password to check.
	 * @param float $timeout Request connection timeout in seconds (default: 5.0).
	 * @param int $ttl Time-to-live for cached results in seconds (default: 432000 = 5 days). 
	 * 			Set 0 to disable caching.
     * 
     * @return bool Return true if the password is found in the breach database, false if not.
     * @throws RuntimeException If the HIBP API request fails.
     * 
     * @example - Examples:
     *```php
	 * // quick check (in-memory + 5days file cache)
	 * $compromised = Password::isCompromised('password123');
	 * 
	 * // disable file cache (still uses in-memory for this process)
	 * $compromised = Password::isCompromised('password123', ttl: 0);
	 * 
	 * // use a shorter ttl (24 hours)
	 * $compromised = Password::isCompromised('password123', ttl: 86400);
	 * ```
	 * 
	 * > **Note:**
	 * > To reduce repeated API calls, results are cached locally by prefix.
     */
	public static function isCompromised(string $password, float $timeout = 5.0, int $ttl = 432000): bool 
	{
		$hash = strtoupper(sha1($password));
		$prefix = substr($hash, 0, 5);
		$suffix = substr($hash, 5);
		$body = null;

		if(array_key_exists($suffix, self::$memory)){
			return self::$memory[$suffix];
		}

		$body = ($ttl > 0) ? self::cache($prefix, $ttl) : null;

		if($body === null){
			try {
				$response = (new Novio([
					'base_uri' => 'https://api.pwnedpasswords.com',
					'timeout'  => $timeout,
				]))->request('GET', "range/{$prefix}");

				$body = $response->getContents();

				if(empty($body)){
					return false;
				}

				if($ttl > 0){
					self::cache($prefix, $ttl, $body);
				}
			} catch (Throwable $e) {
				throw new RuntimeException(
					"HIBP API: " . $e->getMessage(), 
					$e->getCode(), 
					$e
				);
			}
		}

		return self::isPwned($suffix, $body);
	}

	/**
	 * Check if a password contains whitespace.
	 * 
	 * By default, this method detects **any whitespace** in the password
	 * (spaces, tabs, line breaks). You can also restrict it to only check
	 * for **leading or trailing spaces** using the `$startAndEndOnly` flag.
	 * 
	 * @param string $password The password to check.
	 * @param bool $startAndEndOnly If true, only check for leading/trailing spaces (default: false).
	 * 
	 * @return bool Returns true if the password contains whitespace as specified.
	 */
	public static function hasSpaces(string $password, bool $startAndEndOnly = false): bool
	{
		if ($startAndEndOnly) {
			return $password !== trim($password);
		}

		return preg_match('/\s/', $password) === 1;
	}

	/**
	 * Validate a password for common issues.
	 * 
	 * Throws an exception if the password:
	 * - Is empty
	 * - Has leading or trailing spaces
	 * - Contains control or invisible characters
	 * - Contains tabs, carriage returns, or newlines
	 * 
	 * @param string $password Password to validate.
	 * 
	 * @throws InvalidArgumentException On invalid password.
	 */
	private static function assert(string $password): void
	{
		if ($password === '' || $password !== trim($password)) {
			throw new InvalidArgumentException(
				$password === '' 
					? 'Password must not be empty.' 
					: 'Password must not have leading or trailing spaces.'
			);
		}

		if (preg_match('/[\x00-\x1F\x7F]/', $password)) {
			throw new InvalidArgumentException('Password contains invalid or invisible characters.');
		}

		if (preg_match('/[\t\r\n]/', $password)) {
			throw new InvalidArgumentException('Password contains invalid whitespace characters.');
		}
	}

	/**
     * Create an HMAC hash of a PIN using a secret key.
     *
     * @param string $pin The raw numeric PIN.
     * @param string $label The optional label or identifier.
     * @param string|null $algo The hashing algorithm. Defaults to 'sha256'.
     *
     * @return string Returns the hashed result.
     * @throws RuntimeException If the application secret key is missing.
     */
    private static function hashPin(string $pin, string $label, ?string $algo = null): string
    {
        $secret = env('app.key');
        if (empty($secret)) {
            throw new RuntimeException(
				'Missing application key (app.key). Set it in your environment file or generate one by running: php novakit generate:key'
			);
        }

        $algo ??= 'sha256';
        return hash_hmac($algo, "{$pin}:{$label}", $secret);
    }

	/**
	 * Determine which password hashing algorithm should be used.
	 *
	 * Checks the options array for an explicit algorithm (`algorithm` or `algo` key).
	 * If none is provided, defaults to PASSWORD_ARGON2ID if available, otherwise PASSWORD_DEFAULT.
	 *
	 * @param array $options Password hashing options (may contain 'algorithm' or 'algo').
	 * 
	 * @return string Return the algorithm constant to use with password_hash().
	 * @throws InvalidArgumentException If the algorithm provided is invalid.
	 */
	private static function algorithm(array $options): string 
	{
		$algorithm = $options['algorithm'] 
			?? $options['algo'] 
			?? null;

		if ($algorithm === null) {
			return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
		}

		$algorithms = [
			PASSWORD_DEFAULT,
			PASSWORD_BCRYPT,
		];

		if (defined('PASSWORD_ARGON2I')) {
			$algorithms[] = PASSWORD_ARGON2I;
		}

		if (defined('PASSWORD_ARGON2ID')) {
			$algorithms[] = PASSWORD_ARGON2ID;
		}

		if (!in_array($algorithm, $algorithms, true)) {
			throw new InvalidArgumentException('Unsupported password hashing algorithm provided.');
		}

		return $algorithm;
	}

	/**
	 * Check if a SHA-1 password suffix exists in the HIBP response.
	 *
	 * This method parses the API response for a given prefix, compares each
	 * hash suffix with the target suffix, and caches the result in memory.
	 *
	 * @param string $suffix The SHA-1 suffix of the password to check.
	 * @param string $body The raw response body from the HIBP "range" API.
	 *
	 * @return bool Returns true if the password is found (compromised), false otherwise.
	 */
	private static function isPwned(string $suffix, string $body): bool 
	{
		if($body === ''){
			return false;
		}

		foreach (explode("\n", $body) as $line) {
			if ($line === ''){
				continue;
			}

			[$hashSuffix, $count] = array_pad(explode(":", $line, 2), 2, null);

			if ($hashSuffix === $suffix && (int) $count > 0) {
				return self::$memory[$suffix] = true; 
			}
		}

		return self::$memory[$suffix] = false;
	}

	/**
	 * Cache HIBP password range responses locally.
	 * 
	 * This helper method saves or retrieves cached HIBP range results to
	 * reduce API requests. Cached files are stored under `/writeable/temp/hibp/`
	 * and expire after the specified TTL.
	 * 
	 * @param string $prefix The first 5 characters of the SHA-1 hash.
	 * @param int $ttl Cache lifetime in seconds.
	 * @param string|null $body Optional response body to store.
	 * 
	 * @return string|bool|null Returns the cached response string, true on successful write,
	 *               or null if no valid cache exists.
	 */
	private static function cache(string $prefix, int $ttl, ?string $body = null): mixed 
	{
		$path = root('/writeable/temp/hibp/');
		$filepath = "{$path}range_{$prefix}.txt";

		if($body !== null){
			if (!is_dir($path) && !mkdir($path, 0777, true)) {
				return false;
			}

			return @file_put_contents($filepath, $body, LOCK_EX) !== false;
		}

		if (!is_file($filepath)) {
			return null;
		}

		$meta = @filemtime($filepath);

		if ($meta === false || (time() - $meta) > $ttl) {
			return null;
		}

		$body = @file_get_contents($filepath);
    	return ($body === false || $body === '') ? null : $body;
	}
}