<?php 
/**
 * Luminova Framework Default application psr logger class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Logger;

use \Throwable;
use \JsonException;
use \App\Config\Logger;
use \Luminova\Time\Time;
use \Luminova\Http\Request;
use \Psr\Log\AbstractLogger;
use \Luminova\Logger\LogLevel;
use \Luminova\Utility\Helpers;
use \Luminova\Http\Network\IP;
use \Luminova\Http\Client\Novio;
use \Luminova\Components\Email\Mailer;
use \Luminova\Storage\{Archive, Filesystem};
use function \Luminova\Funcs\{root, make_dir};
use \Luminova\Exceptions\{FileException, RuntimeException, InvalidArgumentException};

class NovaLogger extends AbstractLogger
{
    /**
     * Remote debug tracer
     * 
     * @var array $tracer
     */
    protected array $tracer = [];

    /**
     * The maximum log size in bytes.
     * 
     * @var int $maxSize
     */
    protected int $maxSize = 0;

    /**
     * Flag indicating if backup should be created.
     * 
     * @var bool $autoBackup
     */
    protected bool $autoBackup = false;

    /**
     * Log message template format.
     * 
     * @var string|null $logFormat
     */
    protected static ?string $logFormat = null;

    /**
     * Default log path.
     * 
     * @var string|null $path
     */
    private static ?string $path = null;

    /**
     * Original log level before dispatching.
     * 
     * @var string $level
     */
    private string $level = LogLevel::ALERT;

    /**
     * Include context information in telegram logging.
     * 
     * @var bool|null $sendContext
     */
    private static ?bool $sendContext = null;

    /**
     * Default datetime format.
     * 
     * @var string DATEFORMAT
     */
    private const DATEFORMAT = DATE_ATOM; //'Y-m-d\TH:i:s.uP'; 

    /**
     * Create a new logger instance.
     *
     * Initializes logger configuration, resolves storage path, and applies
     * environment-based limits and behaviors.
     *
     * @param string $name Logger identifier (default: "AppLogger").
     * @param string $extension Log file extension (default: ".log").
     * @param bool $useLocking Enable file locking during writes (default: true).
     * @param string|null $logFormat Optional custom log format 
     *              (e.g, `[{time:Y-m-d\TH:i:s.uP}] {level} {name} {message} {context}`).
     */
    public function __construct(
        protected string $name = 'AppLogger',
        protected string $extension = '.log',
        protected bool $useLocking = true,
        ?string $logFormat = null
    ) 
    {
        self::$path ??= root('/writeable/logs/');

        $this->maxSize    = (int) env('logger.max.size', Logger::$maxSize);
        $this->autoBackup = (bool) env('logger.create.backup', Logger::$autoBackup);

        self::$sendContext ??= (bool) env(
            'logger.telegram.send.context',
            Logger::$telegramSendContext
        );

        if ($logFormat !== null) {
            self::setLogFormat($logFormat);
        }

        if($this->extension !== '.log'){
            $ext = trim($this->extension);
            $ext = ltrim($ext, '.');

            if (!preg_match('/^[a-z0-9]+$/i', $ext)) {
                $this->extension = '.log';
                return;
            }

            $this->extension = '.' . strtolower($ext);
        }
    }

    /**
     * Gets the logging system name identifier.
     *
     * @return string Return the logging system name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the active log format.
     *
     * Returns the currently configured log format. If none has been set,
     * it will be resolved once from the environment configuration and
     * cached for subsequent calls.
     *
     * @return string Returns the active log format string.
     */
    public static function getLogFormat(): string
    {
        if (self::$logFormat === null) {
            self::$logFormat = env('logger.log.format')
                ?: '[{time:' . self::DATEFORMAT . '}] {level} {name} {message} {context}';
        }

        return self::$logFormat;
    }

    /**
     * Set a custom log message format.
     *
     * Overrides the global log format used when rendering log messages.
     * Passing an empty string resets the format back to the environment
     * or the default configuration.
     *
     * Supported placeholders:
     * - {level}           Log level (uppercased)
     * - {name}            Logger or channel name
     * - {time:format}     Date/time with optional PHP date() format
     * - {message}         Log message content
     * - {ipaddress}       Client IP address
     * - {referer}         HTTP referer, if available
     * - {useragent}       Client user agent string
     * - {context}         Context payload (JSON in production, readable dump in development)
     *
     * @param string $format Custom format template
     *      (e.g. `[{level}] [{name}] [{time:Y-m-d\TH:i:s.uP}] {message} {referer} {useragent} {context}`).
     *
     * @return string Returns the active log format after assignment.
     */
    public static function setLogFormat(string $format): string
    {
        self::$logFormat = ($format !== '')
            ? $format
            : null;

        return self::getLogFormat();
    }

    /**
     * Store a debug trace for later remote logging.
     *
     * Saves the provided trace data on the logger instance so it can be
     * attached to remote log submissions. The trace is not written to
     * local logs and is not sent via email or Telegram.
     *
     * @param array $trace Debug trace data (stack trace, context, metadata).
     *
     * @return self Returns the instance of NovaLogger class.
     * 
     * > This method only stores the trace and does not trigger logging.
     */
    public function setTracer(array $trace): self
    {
        $this->tracer = $trace;
        return $this;
    }

    /**
     * Sets the log name for application identifier.
     *
     * @param string $name The Logging system name.
     *
     * @return self Returns the instance of NovaLogger class.
     */
    public function setName(string $name): self 
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Sets the maximum size for log files.
     *
     * This method allows setting a custom maximum size for log files. When a log file
     * reaches this size, it may trigger backup or clearing operations depending on
     * other configuration settings.
     *
     * @param int $size The maximum size of the log file in bytes.
     *
     * @return self Returns the instance of NovaLogger class.
     */
    public function setMaxLogSize(int $size): self 
    {
        $this->maxSize = $size;
        return $this;
    }

    /**
     * Enables or disables automatic log file backup based on maximum log size configuration.
     * 
     * When enabled, old log files will be automatically archived 
     * to prevent excessive log size and improve performance.
     * 
     * @param bool $backup Set to `true` to enable auto-backup, `false` to disable it (default: `true`).
     * 
     * @return self Returns the instance of NovaLogger class.
     */
    public function setAutoBackup(bool $backup = true): self 
    {
        $this->autoBackup = $backup;
        return $this;
    }

    /**
     * Sets the log level for the remote and email logging.
     *
     * @param string|int $level The log level to set (e.g., `LogLevel::*`, 'error', 'info', '0', '1').
     *
     * @return self Returns the instance of NovaLogger class.
     * @throws InvalidArgumentException if an invalid log level is specified.
     */
    public function setLevel(string|int $level): self 
    {
        LogLevel::assert($level, __METHOD__);

        $this->level = LogLevel::resolve($level);
        return $this;
    }

    /**
     * Log a message at the given level.
     *
     * @param string|int $level The log level (e.g., "emergency," "error," "info").
     * @param string $message The log message.
     * @param array<string,mixed> $context Optional additional context to include in log.
     *
     * @return void
     * @throws FileException — If unable to write log to file.
     * @throws InvalidArgumentException If an invalid log level is specified.
     */
    public function log($level, $message, array $context = []): void
    {
        LogLevel::assert($level, __METHOD__);

        try {
            $this->write($level, $message, $context);
        } catch (Throwable $e) {
            $this->level = $level;
            $this->e('Logging', $e->getMessage(), $message);
        }
    }

    /**
     * Log an exception message.
     *
     * @param Throwable|string $message The EXCEPTION message to log.
     * @param array<string,mixed> $context Additional context data (optional).
     * 
     * @return void 
     */
    public function exception(Throwable|string $message, array $context = []): void
    {
        $this->log(
            LogLevel::EXCEPTION, 
            ($message instanceof Throwable) ? $message->getMessage() : $message,
            $context
        );
    }

    /**
     * Log an php message.
     *
     * @param string $message The php message to log.
     * @param array<string,mixed> $context Additional context data (optional).
     * 
     * @return void 
     */
    public function php(string $message, array $context = []): void
    {
        $this->log(LogLevel::PHP, $message, $context);
    }

    /**
     * Log an performance metric.
     *
     * @param string $data The profiling data to log.
     * @param array<string,mixed> $context Additional context data (optional).
     * 
     * @return void 
     */
    public function metrics(string $data, array $context = []): void
    {
        $this->log(LogLevel::METRICS, $data, $context);
    }

    /**
     * Logs message to an email asynchronously.
     *
     * @param string $email The recipient email address.
     * @param string $message The message to log.
     * @param array<string,mixed> $context Additional context data (optional).
     *
     * @return void
     * > Note: If error occurs during mailing log, file logger will be used instead.
     * > If exception occurs during mailing log, file logger with level `exception` be used.
     */
    public function mail(string $email, string $message, array $context = []): void 
    {
        if(!$email || !Helpers::isEmail($email)){
            $this->log(LogLevel::CRITICAL, sprintf('Invalid mail logger email address: %s', $email), [
                'originalMessage' => $message,
                'originalContext' => $context,
                'originalLevel' =>  $this->level,
            ]);
            return;
        }

        $body = $this->getEmailTemplate($this->level, $message, $context);

        try {
            $subject = sprintf(
                '%s (v%.1f) - [%s] %s message Log',
                APP_NAME, 
                APP_VERSION, 
                strtoupper($this->level),
                self::$name
            );
            
            if (Mailer::to($email)->subject($subject)->body($body)->text(strip_tags($body))->send()) {
                return;
            }

            $error = "Failed to send email: {$email}";
        } catch (Throwable $e) {
            $error = sprintf(
                'Unexpected error while sending log to email: %s: %s',
                $email,
                $e->getMessage()
            );
        }

        $entry = self::formatMessage($this->level, $message, $this->name, $context) . PHP_EOL;
        $entry .= self::formatMessage($this->level, $error, $this->name);

        try{
            $this->write($this->level, $entry, format: false);
        }catch(Throwable){}
    }

    /**
     * Send a log message to a remote server.
     *
     * This method builds a standard log payload, optionally encrypts it using the
     * shared secret defined in `env('logger.remote.shared.key')`, and sends it
     * to the given URL via HTTP POST.
     *
     * Encryption behavior:
     * - In production: if encryption fails, the log is written to the local file system.
     * - In development: failure to encrypt throws a RuntimeException.
     *
     * Network errors:
     * - If sending the log fails, the message is written locally.
     * - If an exception occurs during sending, it is logged locally with level `exception`.
     *
     * @param string $url The remote URL endpoint to send the log to.
     * @param string $message The log message.
     * @param array<string,mixed> $context Optional additional context data.
     *
     * @return void
     * @throws RuntimeException If encryption fails in a non-production environment.
     */
    public function remote(string $url, string $message, array $context = []): void 
    {
        if(!$url || !Helpers::isUrl($url)){
            $this->log(LogLevel::CRITICAL, sprintf(
                'Invalid network logger URL endpoint: %s', 
                $url
            ), [
                'originalMessage' => $message,
                'originalContext' => $context,
                'originalLevel'   => $this->level,
            ]);
            $this->tracer = [];
            return;
        }

        $request = Request::getInstance();
        $payload = $this->encryptPayload([
            'app'        => APP_NAME,
            'host'       => APP_HOSTNAME,
            'version'    => APP_VERSION,
            'message'    => $message,
            'context'    => $context,
            'tracer'     => $this->tracer,
            'level'      => $this->level,
            'name'       => $this->name,
            'url'        => $request->getUrl(),
            'referer'    => $request->getReferer(false),
            'method'     => $request->getMethod(),
            'ipaddress'  => IP::get(),
            'useragent'  => $request->getUserAgent()->toString(),
        ]);
        

        if($payload === null){
            $this->tracer = [];

            if(PRODUCTION){
                try{
                    $this->write($this->level, $message, $context);
                }catch(Throwable $e){
                    $this->e('Logging', $e->getMessage(), $message);
                }
                return;
            }

            throw new RuntimeException('Failed to encrypt log payload.');
        }

        $this->sendHttpRequest('Remote Server', $url, $payload, $message, $context);
        $this->tracer = [];
    }

    /**
     * Sends a log message to a Telegram chat using the Telegram Bot API.
     *
     * @param string|int $chatId The telegram bot chat ID to send the message to. 
     * @param string $token The telegram bot token.
     * @param string $message The log message to send.
     * @param array<string,mixed> $context Additional contextual data related to the log message.
     *
     * @return void
     */
    public function telegram(string|int $chatId, string $token, string $message, array $context = []): void 
    {
        if (!$token || !$chatId) {
            self::log(LogLevel::CRITICAL, 'Telegram bot token or chat ID is missing', [
                'originalMessage' => $message,
                'originalContext' => $context,
            ]);
            return;
        }

        $request = Request::getInstance();
        $this->sendHttpRequest(
            'Telegram',
            "https://api.telegram.org/bot{$token}/sendMessage",
            [
                'chat_id' => $chatId,
                'text' => sprintf(
                    "<b>Application:</b> %s\n<b>Version:</b> %s\n<b>Host:</b> %s\n<b>IP Address:</b> %s\n<b>Log Name:</b> %s\n<b>Level:</b> %s\n<b>Datetime:</b> %s\n<b>URL:</b> %s\n<b>Method:</b> %s\n<b>Referer:</b> %s\n<b>User-Agent:</b> %s\n\n<pre><code>Message: %s</code></pre>\n\n<pre>%s</pre>",
                    APP_NAME,
                    APP_VERSION,
                    APP_HOSTNAME,
                    IP::get(),
                    $this->name,
                    $this->level,
                    Time::now('UTC')->format(self::DATEFORMAT),
                    $request->getUrl(),
                    $request->getMethod(),
                    $request->getReferer(false),
                    $request->getUserAgent()->toString(),
                    $message,
                    (self::$sendContext && $context !==[]) ? self::toJsonContext($context, true) : ''
                ),
                'parse_mode' => 'HTML'
            ],
            $message,
            $context
        );
    }

    /**
     * Clears the contents of the specified log file for a given log level.
     * 
     * @param string|null $level The log level whose log file should be cleared (e.g., 'info', 'error').
     * 
     * @return bool Returns true on success, or false on failure if the log file cannot be cleared.
     */
    public function clear(?string $level = null): bool 
    {
        $level ??= $this->level;

        if($level === null || !LogLevel::has($level)){
            return false;
        }

        $level = LogLevel::resolve($level);

        $extension = ($level === LogLevel::METRICS) 
            ? '.json' 
            : $this->extension;
            
        return Filesystem::write(self::$path . "{$level}{$extension}", '', $this->useLocking ? LOCK_EX : 0);
    }

    /**
     * Constructs a formatted log message with an ISO 8601 timestamp (including microseconds).
     *
     * @param string|int $level The log severity level (e.g., 'INFO', 'ERROR').
     * @param string $message The main log message.
     * @param array<string,mixed> $context Optional contextual data for the log entry.
     * @param bool $htmlFormat Whether to format the message and context as HTML (default: false).
     *
     * @return string|null Return the formatted log message text/HTML based `$htmlFormat`
     *  or null if invalid log level.
     */
    public function message(
        string|int $level, 
        string $message, 
        array $context = [],
        bool $htmlFormat = false
    ): ?string
    {
        if(!LogLevel::has($level)){
            return null;
        }

        $level = LogLevel::resolve($level);

        return $htmlFormat 
            ? $this->getEmailTemplate($level, $message, $context) 
            : self::formatMessage($level, $message, $this->name, $context);
    }

    /**
     * Creates a backup of the log file if it exceeds a specified maximum size.
     *
     * @param string|int $level The log level to create backup for.
     *
     * @return bool Return true if the backup was created, otherwise false.
     */
    public function backup(string|int $level): bool 
    {
        if(!LogLevel::has($level)){
            return false;
        }

        $level = LogLevel::resolve($level);
        $filepath = self::$path . "{$level}{$this->extension}";

        if($this->maxSize && Filesystem::size($filepath) >= (int) $this->maxSize){

            if(!$this->autoBackup){
                return $this->clear($level);
            }

            $backup = self::$path . 'backups' . DIRECTORY_SEPARATOR;
            
            if(make_dir($backup)){
                $backupTime = Time::now()->format('Ymd_His');
                $backup .= "{$level}_v" . str_replace('.', '_', APP_VERSION) . "_{$backupTime}.zip";

                try{
                    if(Archive::zip($backup, $filepath)){
                        return $this->clear($level);
                    }
                }catch(FileException $e){
                    Filesystem::write(
                        $filepath, 
                        self::formatMessage(
                            $level, 
                            sprintf('Failed to create backup for %s: error: %s', $level, $e->getMessage() . PHP_EOL),
                            $this->name
                        ), 
                        FILE_APPEND|LOCK_EX
                    );
                }
            }
        }

        return false;
    }

    /**
     * Formats a log message with level, name, timestamp, and optional context.
     *
     * This method creates a formatted log message string that includes the log level,
     * logger name, current timestamp, the main message, and optionally, any additional
     * context data.
     *
     * @param string $level The log level (e.g., 'INFO', 'ERROR').
     * @param string $message The main log message.
     * @param string|null $name Optional log system name.
     * @param array $context Additional contextual information for the log entry.
     * @param string|null $format Optional log message format (default).
     *
     * @return string Return the formatted log message. If context is provided, it will be
     *                appended to the message.
     * @throws InvalidArgumentException if an invalid log level is specified.
     */
    public static function formatMessage(
        string $level,
        string $message,
        ?string $name = null,
        array $context = [],
        ?string $format = null
    ): ?string 
    {
        LogLevel::assert($level, __METHOD__);

        $format ??= self::getLogFormat();
        $format = preg_replace_callback(
            '/\{(time(?::[^}]+)?|referer?|useragent?|ipaddress?)\}/',
            static function (array $m): string {
                return match (true) {
                    str_starts_with($m[1], 'time') =>
                        Time::now('UTC')->format(substr($m[1], 5) ?: self::DATEFORMAT),

                    str_starts_with($m[1], 'referer') =>
                        (string) Request::getInstance()->getReferer(false),

                    str_starts_with($m[1], 'ipaddress') => IP::get(),
                    str_starts_with($m[1], 'useragent') =>
                        Request::getInstance()->getUserAgent()->toString(),

                    default => '',
                };
            },
            $format
        );

        $replacements = [
            '{level}'   => strtoupper(LogLevel::resolve($level)),
            '{message}' => $message,
            '{name}'    => '',
            '{context}' => ($context === [])
                ? ''
                : (PRODUCTION
                    ? self::toJsonContext($context, false)
                    : print_r($context, true)),
        ];

        if ($name !== null) {
            $replacements['{name}'] = $name;
        }

        $result = strtr($format, $replacements);
        return preg_replace('/\s+/', ' ', trim($result));
    }

    /**
     * Generates an HTML-formatted log message.
     *
     * This method is called when sending log to email or generating 
     * a formatted HTML representation of a log message.
     *
     * @param string|int $level The log entry level (e.g., `INFO`, `ERROR`).
     * @param string $message The log entry message.
     * @param array $context The additional contextual information passed with log entry.
     *
     * @return string|null Return HTML-formatted string representing the log message and context or null for default.
     */
    protected function toHtmlMessage(string|int $level, string $message,  array $context = []): ?string 
    {
        return null;
    }

    /**
     * Encrypt a log payload for remote transmission.
     *
     * This method encrypts the entire payload using AES-256-GCM if a shared key is provided.
     * Developers can override this method to implement custom encryption strategies
     * or to use alternative ciphers. Returning `null` indicates encryption failed.
     *
     * If no key is provided or OpenSSL is unavailable, the payload is returned unencrypted.
     *
     * @param array<string,mixed> $payload The log payload to encrypt.
     * @param string|null $key Optional encryption key (defaults: `env('logger.remote.shared.key')`).
     *
     * @return array<string,string>|null Returns the encrypted payload with keys `cipher`, `iv`, `tag`, `data`.
     *                    Returns the original payload if no key is available.
     *                    Returns `null` if encryption fails.
     */
    protected function encryptPayload(array $payload, ?string $key = null): ?array
    {
        static $cipher = null;
        $key ??= env('logger.remote.shared.key');

        if(!$key){
            return $payload;
        }
        
        $cipher ??= function_exists('openssl_encrypt');

        if(!$cipher){
            return null;
        }

        try{
            $json = json_encode($payload, JSON_THROW_ON_ERROR);
            $iv = random_bytes(12); 
            $tag = '';

            $data = openssl_encrypt(
                $json,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            if ($data === false) {
                return null;
            }

            return [
                'cipher' => 'aes-256-gcm',
                'iv' => base64_encode($iv),
                'tag' => base64_encode($tag),
                'data' => base64_encode($data),
            ];
        }catch(Throwable){
            return null;
        }
    }

    /**
     * Converts a context array to a JSON string.
     *
     * @param array $context The context data to encode.
     * @param bool $pretty Whether to pretty-print the JSON output.
     *
     * @return string Return the encoded JSON string or an error message.
     */
    private static function toJsonContext(array $context, bool $pretty = false): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        try {
            return json_encode($context, $flags) ?: '';
        } catch (JsonException) {
            try {
                return json_encode(self::sanitizeUtf8($context), $flags) ?: '';
            } catch (JsonException) {
                return print_r($context, true);
            }
        }

        return '';
    }

    /**
     * Try sanitize context if failed.
     * 
     * @param mixed $value The context value to sanitize.
     * 
     * @return mixed Return sanitized context.
     */
    private static function sanitizeUtf8(mixed $value): mixed 
    {
        if (is_string($value)) {
            return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }
    
        if (is_array($value)) {
            return array_map([self::class, 'sanitizeUtf8'], $value);
        }
    
        return $value;
    }    

    /**
     * Writes a log message to the specified file.
     *
     * @param string $level The log level (e.g., 'info', 'error', 'debug').
     * @param string $message The primary log message.
     * @param array<string|int,mixed>  $context Optional associative array providing context data.
     *
     * @return bool Returns true if the log written to file, false otherwise.
     * @throws FileException — If unable to write log to file.
     */
    private function write(
        string $level, 
        string $message, 
        array $context = [],
        bool $format = true
    ): bool
    {
        if(make_dir(self::$path)){
            $level = LogLevel::resolve($level) 
                ?? LogLevel::INFO;

            if($level === LogLevel::METRICS){
                return $this->writeMetric($message, $context['key'] ?? '');
            }

            $path = self::$path . "{$level}{$this->extension}";
            $message = (!$format || str_contains($message, strtoupper($level)))
                ? trim($message, PHP_EOL)
                : self::formatMessage($level, $message, $this->name, $context);

            $flags = FILE_APPEND;

            if($this->useLocking){
                $flags |= LOCK_EX;
            }

            if(Filesystem::write($path, $message . PHP_EOL, $flags)){
                return ($this->autoBackup && $this->maxSize) 
                    ? $this->backup($level) 
                    : true;
            }
        }

        return false;
    }

    /**
     * Logs performance metrics data to a JSON file.
     *
     * @param string $data The performance metrics data to log.
     * @param string $key The unique identifier for the metrics data.
     *
     * @return bool Returns true if the metrics data was successfully logged, false otherwise.
     */
    private function writeMetric(string $data, string $key): bool 
    {
        if(!$data || !$key){
            return false;
        }

        $path = self::$path . 'metrics.json';
        $contents = file_exists($path) ? file_get_contents($path) : null;
        if ($contents === false) {
            return false;
        }

        $key = md5($key);
        $contents = ($contents === null) ? [] : json_decode($contents, true);
        $contents[$key] = $this->normalizeArrayKeys(json_decode($data, true));
        $contents[$key]['info']['DateTime'] = Time::now('UTC')->format(self::DATEFORMAT);

        $updated = json_encode($contents, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        if ($updated === false) {
            return false;
        }

        try{
            return Filesystem::write($path, $updated, $this->useLocking ? LOCK_EX | LOCK_NB : LOCK_NB);
        }catch(Throwable){}
        return false;
    }

    /**
     * Default an HTML-formatted log message.
     *
     * @param string $level The log level (e.g., 'INFO', 'ERROR').
     * @param string $message The main log message.
     * @param array $context Optional. Additional contextual information for the log entry.
     *
     * @return string|null Return HTML-formatted string representing the log message and context or null for default.
     */
    private function formatHtmlMessage(
        string $level, 
        string $message, 
        array $context = []
    ): ?string 
    {
        $html = '<p style="font-size: 14px; color: #555;">No additional context provided.</p>';

        if ($context !== []) {
            $formatter = '<tr>
                <td style="padding: 8px; border: 1px solid #ddd; background-color: #f9f9f9; font-weight: bold;">%s</td>
                <td style="padding: 8px; border: 1px solid #ddd;">%s</td>
            </tr>';

            $html = '<h3 style="font-size: 18px; color: #333; margin-top: 20px; margin-bottom: 10px;">Additional Context</h3>';
            $html .= '<table style="width: 100%; border-collapse: collapse; font-size: 14px;margin-bottom: 15px;">';
            foreach ($context as $key => $value) {
                $html .= sprintf(
                    $formatter,
                    htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars(is_scalar($value) ? $value : json_encode($value), ENT_QUOTES, 'UTF-8')
                );
            }
            $html .= '</table>';
        }

        return sprintf(
            '<p style="font-size: 16px; margin-bottom: 15px;"><strong>[%s]</strong>: %s</p>%s', 
            strtoupper($level), 
            $message, 
            $html
        );
    }

    /**
     * Generates the HTML message body for mail logging.
     * 
     * @param string $level The log level (e.g., 'info', 'error', 'debug').
     * @param string $message The log message.
     * @param array $context Additional context data (optional).
     * 
     * @return string Return formatted HTML email message body.
     */
    private function getEmailTemplate(string $level, string $message, array $context = []): string 
    {
        $html = $this->toHtmlMessage($level, $message, $context);

        if($html){
            return $html;
        }

        return Logger::getEmailLogTemplate(
            Request::getInstance(),
            $this,
            $message,
            $level,
            $context
        ) ?: $this->formatHtmlMessage($level, $message, $context);
    }

    /**
     * Normalizes the keys of an array by removing spaces from them.
     *
     * This function takes an array and removes any spaces from its keys.
     * If the input is not an array or is empty, it returns an empty array.
     *
     * @param mixed $array The input array to normalize.
     *
     * @return array An array with normalized keys (spaces removed), or an empty array if input is invalid.
     */
    private function normalizeArrayKeys(mixed $array): array 
    {
        if(!$array || !is_array($array)){
            return [];
        }

        $normalized = [];

        foreach ($array as $key => $value) {
            $normalized[str_replace(' ', '', (string) $key)] = is_array($value) 
                ? $this->normalizeArrayKeys($value)
                : $value;
        }
    
        return $normalized;
    }

    /**
     * Sends an HTTP request asynchronously using a Fiber.
     *
     * This method sends a POST request to a specified URL with the given body. It handles
     * the request asynchronously and manages potential errors, logging them appropriately.
     *
     * @param string $from The identifier of the source sending the request (e.g., 'Remote Server', 'Telegram').
     * @param string $url The URL to which the HTTP request will be sent.
     * @param array<string,mixed> $body The body of the HTTP request to be sent.
     * @param string $message The original log message that triggered this request.
     * @param array<string,mixed> $context Additional context information for logging purposes (optional).
     *
     * @return void
     */
    private function sendHttpRequest(
        string $from,
        string $url,
        array $body,
        string $message,
        array $context = []
    ): void 
    {
        $ctx = [];
        try {
            $response = (new Novio())->request('POST', $url, [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => $body
            ]);

            $status = $response->getStatusCode();
            $payload = $response->getBody()->toArray();

            $ok = $payload['ok']
                ?? false;

            if ($status === 200 && $ok === true) {
                return;
            }

            $ctx = $payload['context'] ?? [];

            if($ctx !== []){
                $context['log_id'] =  $ctx['log_id'] ?? '';
                $context['log_timestamp'] =  $ctx['timestamp'] ?? '';
            }

            $error = sprintf(
                'Failed to send log to %s: %s',
                $from,
                $payload['description']
                    ?? 'Unknown response error'
            );
        } catch (Throwable $e) {
            $error = sprintf(
                'Unexpected error while sending log to %s: %s',
                $from,
                $e->getMessage()
            );
        }

        $entry  = self::formatMessage($this->level, $message, $this->name, $context) . PHP_EOL;
        $entry .= self::formatMessage($this->level, $error, $this->name, $ctx);

        try{
            $this->write($this->level, $entry, format: false);
        }catch(Throwable){
            $this->e('Logging', $e->getMessage(), $message);
        }
    }

    /**
     * Logs an exception error message with original log information.
     *
     * @param string $from The source or context where the error originated.
     * @param string $error The error message or description.
     * @param string $message The original message that was being logged when the error occurred (optional).
     * @param array  $context Additional contextual data related to the original log attempt (optional).
     *
     * @return void
     */
    private function e(string $from, string $error, string $message): void
    {
        @error_log(sprintf(
            '%s.%s [%s] Error: %s, Exception: %s', 
            $from, 
            $this->name,
            $this->level,
            $message,
            $error
        ));
    }
}