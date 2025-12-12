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
use \Luminova\Luminova;
use \Luminova\Time\Time;
use \Psr\Log\AbstractLogger;
use \Luminova\Http\Network\IP;
use \Luminova\Logger\LogLevel;
use \Luminova\Utility\Helpers;
use \Luminova\Components\Async;
use \Luminova\Components\Email\Mailer;
use \Luminova\Http\{Request, HttpStatus};
use \Luminova\Storage\{Archive, Filesystem};
use function \Luminova\Funcs\{root, make_dir};
use \Luminova\Exceptions\{FileException, RuntimeException, InvalidArgumentException};

class NovaLogger extends AbstractLogger
{
    /**
     * Remote debug tracer.
     * 
     * @var array $tracer
     */
    protected array $tracer = [];

    /**
     * Log file streams.
     * 
     * @var array<string,resource> $streams
     */
    protected static array $streams = [];

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
     * @var string DATEFORMAT (Y-m-d\TH:i:s.uP)
     */
    private const DATEFORMAT = DATE_ATOM;

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
        $this->maxSize = (int) env('logger.max.size', Logger::$maxSize);
        $this->autoBackup = (bool) env('logger.create.backup', Logger::$autoBackup);

        self::$sendContext ??= (bool) env(
            'logger.remote.send.context',
            Logger::$remoteSendContext
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
     * Close file all streams.
     */
    public function __destruct()
    {
        self::closeStreams();
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
        $this->write($level, $message, $context);
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

        $this->write($this->level, $entry, format: false);
    }

    /**
     * Send a log message to a remote server.
     *
     * This method builds a standard log payload, optionally encrypts it using the
     * shared secret defined in `env('logger.remote.sign.key')`, and sends it
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
     * @param string $url The remote endpoint URL to send the log (e.g, `https://example.com/api/logs`).
     * @param string $message The log message content.
     * @param array<string,mixed> $context Optional additional context data.
     *
     * @return void
     * @throws RuntimeException If encryption fails in a non-production environment.
     */
    public function remote(string $url, string $message, array $context = []): void 
    {
        if(!$url || !Helpers::isUrl($url)){
            $this->log(LogLevel::CRITICAL, sprintf(
                'Invalid remote logger endpoint URL: %s', 
                $url
            ), [
                'originalMessage' => $message,
                'originalContext' => $context,
                'originalLevel'   => $this->level,
            ]);
            $this->tracer = [];
            return;
        }

        $data = $this->toRemotePayload($message, $context);
        
        if($data === null){
            $this->tracer = [];

            if(PRODUCTION){
               $this->write($this->level, $message, $context);
                return;
            }

            throw new RuntimeException('Failed to encrypt log payload.');
        }

        $this->flushRemote([
            'from'    => 'Remote Server',
            'url'     => $url,
            'body'    => $data,
            'message' => $message,
            'context' => $context,
            'level'   => $this->level,
            'name'    => $this->name,
        ]);
        $this->tracer = [];
    }

    /**
     * Send a log message to a Telegram chat via the Bot API.
     *
     * Builds a formatted message and posts it to the given chat ID using
     * the provided bot token. If credentials are missing, a critical log
     * entry is recorded and the request is skipped.
     *
     * @param string|int $chatId Target telegram chat ID (user, group, or channel).
     * @param string $token Telegram bot token used for authentication.
     * @param string $message The log message content.
     * @param array<string,mixed> $context Optional context data to append.
     *
     * @return void
     * @link https://api.telegram.org
     */
    public function telegram(string|int $chatId, string $token, string $message, array $context = []): void 
    {
        if (!$token || !$chatId) {
            self::log(LogLevel::CRITICAL, 'Telegram log bot token or chat ID is missing', [
                'originalMessage' => $message,
                'originalContext' => $context,
            ]);
            return;
        }

        $this->flushRemote([
            'from'    => 'Telegram',
            'url'     => "https://api.telegram.org/bot{$token}/sendMessage",
            'body'    => [
                'chat_id' => $chatId,
                'text' => $this->toRemoteMarkdown($message, $context),
                'parse_mode' => 'Markdown'
            ],
            'message' => $message,
            'context' => $context,
            'level'   => $this->level,
            'name'    => $this->name,
        ]);
    }

    /**
     * Send a log message to a Slack channel using an incoming webhook.
     *
     * Formats the message and delivers it to Slack via the webhook URL.
     * If the webhook URL is missing, a critical log entry is recorded
     * and the request is skipped.
     *
     * @param string $webhookUrl Slack incoming webhook URL.
     * @param string $message The log message content.
     * @param array<string,mixed> $context Optional context data to append.
     *
     * @return void
     * @link https://api.slack.com/apps
     */
    public function slack(string $webhookUrl, string $message, array $context = []): void
    {
        if (!$webhookUrl) {
            self::log(LogLevel::CRITICAL, 'Slack log webhook URL is missing', [
                'originalMessage' => $message,
                'originalContext' => $context,
            ]);
            return;
        }

        $this->flushRemote([
            'from'    => 'Slack',
            'url'     => $webhookUrl,
            'body'    => [
                'text' => $this->toRemoteMarkdown($message, $context),
            ],
            'message' => $message,
            'context' => $context,
            'level'   => $this->level,
            'name'    => $this->name,
        ]);
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

        $result = Filesystem::write(
            self::$path . "{$level}{$extension}", 
            '', 
            $this->useLocking ? LOCK_EX : 0
        );

        if($result){
            self::closeStream("{$level}{$extension}");
        }

        return $result;
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
     * @see self::runAutoBackup() for synchronous and asynchronous auto backup handling.
     */
    public function backup(string|int $level): bool 
    {
        if(!LogLevel::has($level)){
            return false;
        }

        $level = LogLevel::resolve($level);
        $filepath = self::$path . "{$level}{$this->extension}";

        if(!is_file($filepath)){
            return false;
        }

        $backup = self::$path . 'backups' . DIRECTORY_SEPARATOR;
        
        if(!make_dir($backup)){
            return false;
        }

        $backupTime = Time::now()->format('Ymd_His');
        $backup .= "{$level}_v" . str_replace('.', '_', APP_VERSION) . "_{$backupTime}.zip";

        try{
            if(Archive::zip($backup, $filepath)){
                return $this->clear($level);
            }
        }catch(FileException $e){
            self::append(self::formatMessage(
                $level, 
                sprintf('Failed to create backup for %s: error: %s', $level, $e->getMessage()),
                $this->name
            ), "{$level}{$this->extension}", true);
        }

        return false;
    }

    /**
     * Auto backup log file when maximum size is reached.
     * 
     * This method creates a backup of the log file in non-blocking asynchronous background process.
     *
     * @param string $level The log level to check for auto-backup.
     * @param bool $async Whether to perform the backup asynchronously (default: true).
     * @param string|null $filename Optional specific log file path.
     *
     * @return void
     * @see self::backup() for synchronous backup handling.
     */
    public final function runAutoBackup(string $level, bool $async = true, ?string $filename = null): void 
    {
        if(!$this->maxSize){
            return;
        }

        $size = self::getStreamSize(($filename === null) 
            ? "{$level}{$this->extension}" 
            : basename($filename)
        );

        if($size < (int) $this->maxSize){
            return;
        }

        if(!$this->autoBackup){
            $this->clear($level);
            return;
        }

        if(!$async){
            $this->backup($level);
            return;
        }

        Async::background(
            [static::class, '__runAutoBackup'],
            arguments: [
                'name' => $this->name,
                'level' => $level
            ],
            lazyRun: false,
            noOutput: true
        )->flush();
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
     * Determine whether a valid open stream exists for the given basename.
     *
     * @param string $basename The log file basename (e.g. "debug.log").
     *
     * @return bool True if a valid stream resource exists, false otherwise.
     */
    protected static function isStream(string $basename): bool
    {
        return isset(self::$streams[$basename]) 
            && is_resource(self::$streams[$basename]);
    }

    /**
     * Retrieve an open stream for the given log file basename.
     *
     * If no stream exists, a new one is opened in append mode and stored.
     * Returns null if the file does not exist or cannot be opened.
     *
     * @param string $basename The log file basename.
     *
     * @return resource|null The stream resource, or null on failure.
     */
    protected static function getStream(string $basename): mixed
    {
        if (!self::isStream($basename)) {
            $filename = self::$path . $basename;

            if (!is_file($filename)) {
                return null;
            }

            $stream = fopen($filename, 'ab');

            if ($stream === false) {
                return null;
            }

            self::$streams[$basename] = $stream;
        }

        return self::$streams[$basename];
    }

    /**
     * Append a log message to the specified file.
     *
     * Writes the message with a newline using a persistent stream.
     * Optionally applies an exclusive lock during the write operation.
     *
     * @param string $message The log message to write.
     * @param string $basename The target log file basename.
     * @param bool $useLocking Whether to apply file locking during write.
     *
     * @return bool True on success, false on failure.
     */
    protected static function append(
        string $message, 
        string $basename, 
        bool $useLocking,
        bool $waitLock = false
    ): bool
    {
        $stream = self::getStream($basename);

        if ($stream === null) {
            return false;
        }

        $locked = false;

        if ($useLocking) {
            if($waitLock){
                $tries = 5;
                $wait = 100_000;

                while (!$locked && $tries-- > 0) {
                    $locked = flock($stream, LOCK_EX | LOCK_NB);
                    if (!$locked) {
                        usleep($wait);
                    }
                }
            }else{
                $locked = flock($stream, LOCK_EX);
            }

            if (!$locked) {
                return false;
            }
        }

        $data = $message . PHP_EOL;
        $length = strlen($data);
        $written = fwrite($stream, $data);

        if ($useLocking && $locked) {
            flock($stream, LOCK_UN);
        }

        if ($written !== $length) {
            return false;
        }

        fflush($stream);
        return true;
    }

    /**
     * Get the current size of the log file.
     *
     * Uses the active stream when available, otherwise falls back to filesystem checks.
     *
     * @param string $basename The log file basename.
     *
     * @return int File size in bytes, or 0 if unavailable.
     */
    protected static function getStreamSize(string $basename): int
    {
        if (self::isStream($basename)) {
            try {
                return Filesystem::size(self::$streams[$basename]);
            } catch (Throwable) {
                return 0;
            }
        }

        $filename = self::$path . $basename;
        clearstatcache(true, $filename);

        return is_file($filename) ? filesize($filename) : 0;
    }

    /**
     * Close and remove a specific stream.
     *
     * Flushes any buffered data before closing the resource.
     *
     * @param string $basename The log file basename.
     *
     * @return void
     */
    protected static function closeStream(string $basename): void
    {
        if (!isset(self::$streams[$basename])) {
            return;
        }

        $stream = self::$streams[$basename];

        if (is_resource($stream)) {
            fflush($stream);
            fclose($stream);
        }

        unset(self::$streams[$basename]);
    }

    /**
     * Close all open log streams.
     *
     * Ensures all buffered data is flushed and resources are released.
     *
     * @return void
     */
    public static function closeStreams(): void
    {
        foreach (self::$streams as $stream) {
            if (is_resource($stream)) {
                fflush($stream);
                fclose($stream);
            }
        }

        self::$streams = [];
    }

    /**
     * Encrypt a log payload for remote transmission.
     *
     * Uses AES-256-GCM (or a custom cipher) to encrypt the payload when a key is provided.
     * The payload is JSON-encoded and encrypted using OpenSSL.
     *
     * Behavior:
     * - Returns encrypted data when successful
     * - Returns null when encryption cannot be performed or fails
     *
     * You may override this method to use a different encryption strategy.
     *
     * @param array<string,mixed> $payload Data to encrypt.
     * @param string $key Encryption key (must not be empty).
     * @param string $algo Cipher algorithm (default: aes-256-gcm).
     * @param bool $hashKey Weather to hash key or key as raw (default: false).
     *          If true key will be hashed using `sha256` and return binary format.
     *
     * @return array{cipher:string,iv:string,tag:string,data:string}|null Return encrypted payload or null if failed
     */
    protected function encryptPayload(
        array $payload,
        string $key,
        string $algo = 'aes-256-gcm',
        bool $hashKey = false
    ): ?array 
    {
        if ($key === '' || !$algo || !function_exists('openssl_encrypt')) {
            return null;
        }

        static $cyphers = null;
        $algo = strtoupper($algo);

        $cyphers ??= array_map(
            'strtoupper',
            openssl_get_cipher_methods()
        );

        if (!in_array($algo, $cyphers, true)) {
            return null;
        }

        if(!$hashKey){
            $key = hash('sha256', $key, true);
        }

        try {
            $json = json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );

            $iv = '';

            if (!str_contains($algo, 'ECB')) {
                $ivLength = openssl_cipher_iv_length($algo);

                if ($ivLength === false) {
                    return null;
                }

                $iv = random_bytes($ivLength);
            }

            $tag = '';
            $encrypted = openssl_encrypt(
                $json,
                $algo,
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            if ($encrypted === false) {
                return null;
            }

            return [
                'cipher' => $algo,
                'iv'     => $iv ? base64_encode($iv) : '',
                'tag'    => $tag ? base64_encode($tag) : '',
                'data'   => base64_encode($encrypted),
            ];
        } catch (Throwable) {
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
                return json_encode(self::toUtf8($context), $flags) ?: '';
            } catch (JsonException) {
                return print_r($context, true);
            }
        }

        return '';
    }

    /**
     * Build a markdown formatted log message for remote delivery.
     *
     * Generates a structured message including application metadata,
     * execution environment (CLI or HTTP), the main message, and optional
     * context data. Output is formatted using Markdown syntax.
     *
     * @param string $message Primary log message content.
     * @param array<string,mixed> $context Optional context data to include.
     * @param bool $isMarkdown Whether to format output using Markdown.
     *
     * @return string Formatted message ready for remote transport.
     */
    protected function toRemoteMarkdown(string $message, array $context = [], bool $isMarkdown = true): string
    {
        $environment = '';
        $isSendContext = (self::$sendContext && $context !== []);

        if (Luminova::isCommand()) {
            $argv = $_SERVER['argv'] ?? [];

            $command = implode(' ', array_map('escapeshellarg', $argv));
            $script = $argv[0] ?? '';

            $environment = "*Environment:* CLI\n"
                . "*Script:* `{$script}`\n"
                . "*Command:* `{$command}`\n"
                . "*Args:* " . implode(', ', array_slice($argv, 1)) . "\n"
                . "*Working Dir:* `" . getcwd() . "`\n"
                . "*PHP:* `" . PHP_VERSION . "`\n";
        } else {
            $request = Request::getInstance();
            $ip = IP::get();

            $environment = "*Environment:* HTTP\n"
                . "*URL:* {$request->getUrl()}\n"
                . "*Method:* {$request->getMethod()}\n"
                . "*Referer:* {$request->getReferer(false)}\n"
                . "*User-Agent:* {$request->getUserAgent()->toString()}\n"
                . "*IP Address:* {$ip}\n";
        }

        return sprintf(
            "*Application:* %s\n*Version:* %s\n*Host:* %s\n*Log Name:* %s\n*Level:* %s\n*Datetime:* %s\n\n%s\n```Message:\n%s```%s",
            APP_NAME,
            APP_VERSION,
            APP_HOSTNAME,
            $this->name,
            $this->level,
            Time::now('UTC')->format(self::DATEFORMAT),
            $environment,
            $message,
            $isSendContext ? "\n\n```Context:\n" . self::toJsonContext($context, true) . '```' : ''
        );
    }

    /**
     * Build and optionally sign log message for remote delivery.
     *
     * Generates a structured message including application metadata,
     * execution environment (CLI or HTTP), the main message, and optional
     * context data.
     *
     * @param string $message Primary log message content.
     * @param array<string,mixed> $context Optional context data to include.
     * @param bool $isMarkdown Whether to format output using Markdown.
     *
     * @return array|null Return remote message payload for remote transport or null if failed to sign.
     */
    protected function toRemotePayload(string $message, array $context): ?array 
    {
        $data = [
            'app'        => APP_NAME,
            'host'       => APP_HOSTNAME,
            'version'    => APP_VERSION,
            'message'    => $message,
            'context'    => (self::$sendContext && $context !== []) ? $context :  [],
            'tracer'     => $this->tracer,
            'level'      => $this->level,
            'name'       => $this->name,
        ];

        if (Luminova::isCommand()) {
            $argv = $_SERVER['argv'] ?? [];

            $data['env']     = 'CLI';
            $data['script']  = $argv[0] ?? '';
            $data['command'] = implode(' ', $argv);
            $data['args']    = array_slice($argv, 1);
            $data['cwd']     = getcwd();
            $data['php']     = PHP_VERSION;

        } else {
            $request = Request::getInstance();

            $data['env']       = 'HTTP';
            $data['url']       = $request->getUrl();
            $data['method']    = $request->getMethod();
            $data['referer']   = $request->getReferer(false);
            $data['agent']     = $request->getUserAgent()->toString();
            $data['ipaddress'] = IP::get();
        }

        $key = env('logger.remote.sign.key') ?: env('logger.remote.shared.key');

        if(!$key){
            return $data;
        }

        return $this->encryptPayload($data, $key);
    }

    /**
     * Recursively convert values to UTF-8.
     *
     * Handles strings, arrays, and objects safely.
     *
     * @param mixed $value The value to convert.
     * @param array &$seen Internal reference tracker to prevent recursion loops.
     * 
     * @return mixed UTF-8 converted value.
     */
    private static function toUtf8(mixed $value, array &$seen = []): mixed
    {
        if (is_string($value)) {
            return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        $id = is_object($value) ? spl_object_id($value) : null;

        if ($id !== null && isset($seen[$id])) {
            return $value;
        }

        if ($id !== null) {
            $seen[$id] = true;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::toUtf8($item, $seen);
            }

            return $value;
        }

        if (is_object($value)) {
            foreach (get_object_vars($value) as $prop => $propValue) {
                $value->$prop = self::toUtf8($propValue, $seen);
            }
            return $value;
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
        if(!make_dir(self::$path)){
            return false;
        }

        $level = LogLevel::resolve($level) 
            ?? LogLevel::INFO;

        if($level === LogLevel::METRICS){
            return $this->writeMetric($message, $context['key'] ?? '');
        }

        $finalMessage = (!$format || str_contains($message, strtoupper($level)))
            ? trim($message, PHP_EOL)
            : self::formatMessage($level, $message, $this->name, $context);

        $basename = "{$level}{$this->extension}";

        try{
            $logged = self::append(
                $finalMessage,
                $basename,
                $this->useLocking
            );
        } catch(Throwable $e){
            $this->level = $level;
            $this->e('Logging', $e->getMessage(), $message);
        } finally {
            if($logged){
                $this->runAutoBackup($level, filename: self::$path . $basename);
            }
        }

        return $logged;
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
     * Flushes the remote log message asynchronously or synchronously based on configuration.
     *
     * @param array $options The options containing.
     *
     * @return void
     */
    private function flushRemote(array $options): void 
    {
        if(!env('logger.remote.async', Logger::$remoteAsyncLogging)){
            $err = self::__flushRemote($options, false);

            if($err !== null){
                $this->write($this->level, $err, format: false);
            }

            return;
        }

        Async::background(
            [static::class, '__flushRemote'],
            arguments: $options,
            lazyRun: false,
            noOutput: true
        )->flush();
    }

    /**
     * Auto backup log file when maximum size is reached.
     *
     * @param array{name:string,level:string} $args The arguments containing background arguments.
     *
     * @return bool Return true if the backup was created, otherwise false.
     * @internal Used by Async background process.
     * @ignore Use {@see self::runAutoBackup()} instead.
     */
    public static final function __runAutoBackup(array $args): bool 
    {
        return (new \Luminova\Logger\NovaLogger(name: $args['name']))
            ->backup($args['level']);
    }

    /**
     * Sends a log message to a remote server.
     *
     * This static method builds a standard log payload and sends it to the given URL via HTTP POST. 
     * It handles network errors and logs any failures locally.
     *
     * @param array{from:string,url:string,body:array,level:string,message:string,name:string,context:array} $args 
     *                  The arguments containing background arguments.
     * @param bool $async Whether to log asynchronously or not (default: true).
     *
     * @return void
     * @internal Used by Async background process.
     * @ignore
     */
    public static final function __flushRemote(array $args, bool $async = true): ?string 
    {
        $ctx = [];
        $context = $args['context'] ?? [];

        try {
            $response = (new \Luminova\Http\Client\Novio())
                ->request('POST', $args['url'], [
                    'headers' => ['Content-Type' => 'application/json'],
                    'body' => $args['body']
                ]);

            $status = $response->getStatusCode();

            if ($status === 200 || HttpStatus::isSuccess($status)) {
                return null;
            }

            $body = $response->getBody();
            $payload = $body->toArray() ?: $body->buffer();
            $ok = ($payload === 'ok' || ($payload['ok'] ?? false));

            if ($ok === true) {
                return null;
            }

            $ctx = $payload['context'] ?? [];

            if($ctx !== []){
                $context['log_id'] =  $ctx['log_id'] ?? '';
                $context['log_timestamp'] =  $ctx['timestamp'] ?? '';
            }

            $error = sprintf(
                'Failed to send log to %s: %s',
                $args['from'],
                $payload['description']
                    ?? 'Unknown response error'
            );
        } catch (Throwable $e) {
            $error = sprintf(
                'Unexpected error while sending log to %s: %s',
                $args['from'],
                $e->getMessage()
            );
        }

        $entry  = \Luminova\Logger\NovaLogger::formatMessage(
            $args['level'], 
            $args['message'], 
            $args['name'], 
            $context
        ) . PHP_EOL;

        $entry .= \Luminova\Logger\NovaLogger::formatMessage(
            $args['level'], 
            $error, 
            $args['name'], 
            $ctx
        );

        if(!$async){
            return $entry;
        }

        (new \Luminova\Logger\NovaLogger(name: $args['name']))
            ->log($args['level'], $entry);

        return null;
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