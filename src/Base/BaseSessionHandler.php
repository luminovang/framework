<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Base;

use \SessionHandlerInterface;
use \SessionIdInterface;
use \Luminova\Logger\Logger;
use \App\Config\Session;
use \Luminova\Exceptions\RuntimeException;

abstract class BaseSessionHandler implements SessionHandlerInterface, SessionIdInterface
{
    /**
     * Computed session ID length.
     * 
     * @var int|null $sidLength
     */
    private static ?int $sidLength = null;

    /**
     * Session config.
     * 
     * @var Session|null $config
     */
    protected ?Session $config = null;

    /**
     * Last session data hashed value.
     * 
     * @var string $fileHash
     */
    protected string $fileHash = '';

    /**
     * Session ID validation pattern.
     * 
     * @var string $pattern
     */
    protected string $pattern = '';

    /**
     * Configuration options for session handling.
     * 
     * @var array<string,mixed> $options
     */
    protected array $options = [
        'encryption'        => false,
        'session_ip'        => false,
        'debugging'         => false,
        'cacheable'         => false, // database handler
        'sid_entropy_bits'  => 160,
        'dir_permission'    => 0777, // file handler
        'columnPrefix'      => null, // database handler
        'autoLockDatabase'  => false, // database handler
        'onValidate'        => null,
        'onCreate'          => null,
        'onClose'           => null
    ];

    /**
     * Constructor to initialize the session handler.
     *
     * @param array<string,mixed> $options Configuration options for session handling.
     * 
     * @throws RuntimeException if an error occurred.
     * 
     * @link https://luminova.ng/docs/0.0.0/sessions/database-handler
     * @link https://luminova.ng/docs/0.0.0/sessions/filesystem-handler
     */
    public function __construct(array $options = [])
    {
        $this->options = array_replace($this->options, $options);
        $this->pattern = self::initSessionIDPattern((int) $this->options['sid_entropy_bits']);
    }

    /**
     * Set session configuration.
     * 
     * @param Session<Luminova\Base\BaseConfig> $config The session configuration object.
     * 
     * @return void
     */
    public function setConfig(Session $config): void 
    {
        $this->config = $config;
    }

    /**
     * Generate a new session based session id length and bits.
     *
     * @return string Return a unique session ID.
     */
    public function create_sid(): string
    {
        $bitsPerCharacter = (int) ini_get('session.sid_bits_per_character');
        self::$sidLength ??= (int) ini_get('session.sid_length');
  
        $totalBits = $bitsPerCharacter * self::$sidLength;
        $bytes = random_bytes((int) ceil($totalBits / 8));

        $sid = match ($bitsPerCharacter) {
            4 => bin2hex($bytes), 
            5 => self::base32Encode($bytes),
            6 => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '='),
            default => throw new RuntimeException('Unsupported session.sid_bits_per_character value.')
        };

        return substr($sid, 0, self::$sidLength);
    }

    /**
     * Updates the session timestamp.
     *
     * @param string $id The session ID.
     * @param string $data The session data.
     * 
     * @return bool Return true on success, false on failure.
     */
    public function update_timestamp(string $id, string $data): bool
    {
        return $this->write($id, $data);
    }

    /**
     * Initializes the session ID pattern and updates session length settings if required.
     * 
     * @param int $bits The total number of bits to be used for the session ID (e.g., `160`, `130`).
     *
     * @return string Returns the generated regex pattern for the session ID.
     *                The pattern ensures compatibility with the character set and length.
     * 
     * @throws RuntimeException If an unsupported value for `session.sid_bits_per_character` is encountered.
     */
    protected static function initSessionIDPattern(int $bits): string
    {
        $bitsPerCharacter = (int) ini_get('session.sid_bits_per_character');
        $sidLength = (int) ini_get('session.sid_length');
        $newSidLength = (int) ceil($bits / $bitsPerCharacter);

        $updatable = ($newSidLength !== $sidLength);
        self::$sidLength = $updatable ? $newSidLength : $sidLength;

        if ($bitsPerCharacter * self::$sidLength < $bits) {
            $updatable = true;
            self::$sidLength = (int) ceil($bits / $bitsPerCharacter);
        }

        if($updatable){
            ini_set('session.sid_length', (string) self::$sidLength);
        }

        $pattern = match ($bitsPerCharacter) {
            4 => '[0-9a-f]',
            5 => '[0-9a-v]',
            6 => '[0-9a-zA-Z,-]',
            default => throw new RuntimeException('Unsupported session.sid_bits_per_character value.')
        };

        return $pattern . '{' . self::$sidLength . '}';
    }

    /**
     * Encodes a string into Base32 using a for session ids.
     *
     * @param string $input The input string to be encoded.
     *                      It is expected to be ASCII or binary data.
     *
     * @return string Return the Base32 encoded output string.
     *                The result will only contain characters from the a-v Base32 alphabet.
     */
    protected static function base32Encode(string $input): string
    {
        $alphabet = '0123456789abcdefghijklmnopqrstuv';
        $output = '';
        $binaryString = '';
        
        foreach (str_split($input) as $byte) {
            $binaryString .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $length = strlen($binaryString);
        for ($i = 0; $i < $length; $i += 5) {
            $chunk = substr($binaryString, $i, 5);
            $output .= $alphabet[bindec($chunk)];
        }

        return $output;
    }

    /**
     * Destroy the session cookie.
     * 
     * @return bool Return true on success, false on failure.
     */
    protected function destroySessionCookie(): bool
    {
        $params = $this->config ? [
            'cookieName' => $this->config->cookieName,
            'path' => $this->config->sessionPath,
            'domain' => $this->config->sessionDomain,
        ] : session_get_cookie_params();

        return $params ? setcookie(
            $params['cookieName'] ?? session_name(),
            '',
            [
                'expires' => time() - 42000, 
                'path' => $params['path'], 
                'domain' => $params['domain'], 
                'secure' => true, 
                'httponly' => true
            ]
        ): false;
    }

    /**
     * Log error messages for debugging purposes.
     *
     * @param string $level The log level.
     * @param string $message The log message.
     *
     * @return void
     */
    protected function log(string $level, string $message): void
    {
        if($this->options['debugging']){
            Logger::dispatch($level, $message);
        }
    }
}