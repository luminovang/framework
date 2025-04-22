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

use \Psr\Log\AbstractLogger;
use \App\Config\Logger;
use \Luminova\Logger\LogLevel;
use \Luminova\Time\Time;
use \Luminova\Functions\Func;
use \Luminova\Functions\IP;
use \Luminova\Email\Mailer;
use \Luminova\Http\Network;
use \Luminova\Http\Request;
use \Luminova\Storages\FileManager;
use \Luminova\Storages\Archive;
use \Luminova\Exceptions\FileException;
use \Luminova\Exceptions\InvalidArgumentException;
use \Luminova\Exceptions\AppException;
use \JsonException;
use \Throwable;
use \Fiber;

class NovaLogger extends AbstractLogger
{
    /**
     * Default log path.
     * 
     * @var string|null $path
     */
    private static ?string $path = null;

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
     * HTTP Request class.
     * 
     * @var Request $request
     */
    private static ?Request $request = null;

    /**
     * Original log level before dispatching.
     * 
     * @var string $level
     */
    private string $level = LogLevel::ALERT;

    /**
     * Initialize NovaLogger class.
     * 
     * @param string $name The Logging system identifier name (default: `default`).
     * @param string $extension The log file name extension (default: `.log`).
     */
    public function __construct(
        protected string $name = 'default', 
        protected string $extension = '.log'
    )
    {
        self::$path ??= root('/writeable/logs/');
        $this->maxSize = (int) env('logger.max.size', Logger::$maxSize);
        $this->autoBackup = env('logger.create.backup', Logger::$autoBackup);
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
     * @param string $level The log level to set (e.g., `LogLevel::*`, 'error', 'info', 'debug').
     *
     * @return self Returns the instance of NovaLogger class.
     * @throws InvalidArgumentException if an invalid log level is specified.
     */
    public function setLevel(string $level): self 
    {
        LogLevel::assert($level, __METHOD__);
        $this->level = $level;
        return $this;
    }

    /**
     * Log a message at the given level.
     *
     * @param string $level The log level (e.g., "emergency," "error," "info").
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
        if(!Logger::$asyncLogging){
            $this->write($level, $message, $context);
            return;
        }

        $fiber = new Fiber(function () use ($level, $message, $context) {
            try {
                $this->write($level, $message, $context);
            } catch (Throwable $e) {
                $this->level = $level;
                $this->e(
                    'Logging', $e->getMessage(), 
                    $message, $context
                );
            }
        });
        
        $fiber->start();
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
        if(!$email || !Func::isEmail($email)){
            $this->log(LogLevel::CRITICAL, sprintf('Invalid mail logger email address: %s', $email), [
                'originalMessage' => $message,
                'originalContext' => $context,
                'originalLevel' =>  $this->level,
            ]);
            return;
        }

        $body = $this->getEmailTemplate($this->level, $message, $context);
        $subject = sprintf(
            '%s (v%.1f) - [%s] %s message Log',
            APP_NAME, 
            APP_VERSION, 
            strtoupper($this->level),
            self::$name
        );
        $fiber = new Fiber(function () use ($email, $subject, $body, $message, $context) {
            try {
                if (!Mailer::to($email)->subject($subject)->body($body)->text(strip_tags($body))->send()) {
                    $this->write(
                        $this->level, 
                        "Failed to send email log: {$message}", 
                        $context
                    );
                }
            } catch (AppException $e) {
                $this->e(
                    'Mailer Error', $e->getMessage(), 
                    $message, $context
                );
            } catch (Throwable $fe) {
                $this->e(
                    'Unexpected Mailer Error', $fe->getMessage(), 
                    $message, $context
                );
            }
        });

        $fiber->start();
    }

    /**
     * Send log message to a remote URL asynchronously.
     *
     * @param string $url The URL to which the log should be sent.
     * @param string $message The message to log.
     * @param array<string,mixed> $context Additional context data (optional).
     *
     * @return void
     * > Note: If error occurs during network log, file logger will be used instead.
     * > If exception occurs during network log, file logger with level `exception` be used.
     */
    public function remote(string $url, string $message, array $context = []): void 
    {
        if(!$url || !Func::isUrl($url)){
            $this->log(LogLevel::CRITICAL, sprintf('Invalid network logger URL endpoint: %s', $url), [
                'originalMessage' => $message,
                'originalContext' => $context,
                'originalLevel' =>  $this->level,
            ]);
            return;
        }

        self::$request ??= new Request();
        $payload = [
            'app'        => APP_NAME,
            'host'       => APP_HOSTNAME,
            'clientIp'   => IP::get(),
            'message'    => self::formatMessage($this->level, $message, $this->name),
            'context'    => $context,
            'level'      => $this->level,
            'name'       => $this->name,
            'version'    => APP_VERSION,
            'url'        => self::$request->getUrl(),
            'method'     => self::$request->getMethod(),
            'userAgent'  => self::$request->getUserAgent()->toString(),
        ];

        $this->sendHttpRequest('Remote Server', $url, $payload, $message, $context);
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

        self::$request ??= new Request();
        $this->sendHttpRequest(
            'Telegram',
            "https://api.telegram.org/bot{$token}/sendMessage",
            [
                'chat_id' => $chatId,
                'text' => sprintf(
                    "<b>Application:</b> %s\n<b>Version:</b> %s\n<b>Host:</b> %s\n<b>Client IP:</b> %s\n<b>Log Name:</b> %s\n<b>Level:</b> %s\n<b>Datetime:</b> %s\n\n<b>Message:</b> %s\n\n<b>URL:</b> %s\n<b>Method:</b> %s\n<b>User-Agent:</b> %s",
                    APP_NAME,
                    APP_VERSION,
                    APP_HOSTNAME,
                    IP::get(),
                    $this->name,
                    $this->level,
                    Time::now()->format('Y-m-d\TH:i:s.uP'),
                    $message,
                    self::$request->getUrl(),
                    self::$request->getMethod(),
                    self::$request->getUserAgent()->toString()
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
     * @param string $level The log level whose log file should be cleared (e.g., 'info', 'error').
     * 
     * @return bool Returns true on success, or false on failure if the log file cannot be cleared.
     */
    public function clear(string $level): bool 
    {
        $extension = ($level === LogLevel::METRICS) 
            ? '.json' 
            : $this->extension;
            
        return FileManager::write(self::$path . "{$level}{$extension}", '', LOCK_EX);
    }

    /**
     * Constructs a formatted log message with an ISO 8601 timestamp (including microseconds).
     *
     * @param string $level The log severity level (e.g., 'INFO', 'ERROR').
     * @param string $message The main log message.
     * @param array<string,mixed> $context Optional contextual data for the log entry.
     * @param bool $htmlFormat Whether to format the message and context as HTML (default: false).
     *
     * @return string The formatted log message in plain text or HTML.
     */
    public function message(
        string $level, 
        string $message, 
        array $context = [],
        bool $htmlFormat = false
    ): string
    {
        return $htmlFormat 
            ? $this->getHtmlMessage($level, $message, $context) 
            : self::formatMessage($level, $message, $this->name, $context);
    }

    /**
     * Creates a backup of the log file if it exceeds a specified maximum size.
     *
     * @param string $level The log level to create backup for.
     *
     * @return bool Return true if the backup was created, otherwise false.
     */
    public function backup(string $level): bool 
    {
        $filepath = self::$path . "{$level}{$this->extension}";

        if($this->maxSize && FileManager::size($filepath) >= (int) $this->maxSize){

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
                    FileManager::write(
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
     * Generates the HTML message body for mail logging.
     * 
     * @param string $level The log level (e.g., 'info', 'error', 'debug').
     * @param string $message The log message.
     * @param array $context Additional context data (optional).
     * 
     * @return string Return formatted HTML email message body.
     */
    protected function getEmailTemplate(string $level, string $message, array $context = []): string 
    {
        self::$request ??= new Request();
        return Logger::getEmailLogTemplate(
            self::$request,
            $this,
            $message,
            $level,
            $context
        ) ?: $this->getHtmlMessage($level, $message, $context);
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
    private function write(string $level, string $message, array $context = []): bool
    {
        if(make_dir(self::$path)){
            if($level === LogLevel::METRICS){
                return $this->writeMetric($message, $context['key'] ?? '');
            }

            $level = LogLevel::LEVELS[$level] ?? LogLevel::INFO;
            $path = self::$path . "{$level}{$this->extension}";
            $message = str_contains($message, '[' . strtoupper($level) . '] [') 
                ? trim($message, PHP_EOL)
                : self::formatMessage($level, $message, $this->name, $context);

            if(FileManager::write($path, $message . PHP_EOL, FILE_APPEND|LOCK_EX)){
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
     * @param string $key  The unique identifier for the metrics data.
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
        $contents[$key]['info']['DateTime'] = Time::now()->format('Y-m-d\TH:i:s.uP');
        
        if ($contents[$key] === null) {
            return false;
        }

        $updated = json_encode($contents, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($updated === false) {
            return false;
        }

        return FileManager::write($path, $updated, LOCK_EX);
    }

    /**
     * Formats a log message with level, name, timestamp, and optional context.
     *
     * This method creates a formatted log message string that includes the log level,
     * logger name, current timestamp, the main message, and optionally, any additional
     * context data.
     *
     * @param string $level   The log level (e.g., 'INFO', 'ERROR').
     * @param string $message The main log message.
     * @param string|null $name Optional log system name.
     * @param array  $context Optional. Additional contextual information for the log entry.
     *                        Default is an empty array.
     *
     * @return string The formatted log message. If context is provided, it will be
     *                appended to the message.
     */
    public static function formatMessage(
        string $level, 
        string $message, 
        ?string $name = null, 
        array $context = []
    ): string 
    {
        $message = sprintf(
            $name ? '[%s] [%s] [%s]: %s' : '[%s]%s [%s]: %s', 
            strtoupper($level), 
            $name ?? '',
            Time::now()->format('Y-m-d\TH:i:s.uP'), 
            $message
        );
        
        if($context === []){
            return $message;
        }

        $message .= ' [CONTEXT] ';

        if (!PRODUCTION) {
            $message .= print_r($context, true);

            return $message;
        }

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

        try {
            $message .= json_encode($context, $flags);
        } catch (JsonException $e) {
            try {
                $message .= json_encode(self::sanitizeUtf8($context), $flags);
            } catch (JsonException $e) {
                $message .= '[Context JSON Error: ' . $e->getMessage() . '] ' . print_r($context, true);
            }
        }
        
        return $message;
    }    

    /**
     * Try sanitize context if failed.
     * 
     * @param mixed $value The context value to sanitize.
     * 
     * @return mixed Return sanitived context.
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
     * Generates an HTML-formatted log message.
     *
     * This method creates an HTML representation of a log message, including the log level,
     * the main message, and any additional context data. The context is formatted as a table
     * if present.
     *
     * @param string $level   The log level (e.g., 'INFO', 'ERROR').
     * @param string $message The main log message.
     * @param array  $context Optional. Additional contextual information for the log entry.
     *                        Default is an empty array.
     * @param string $name    Optional. The name of the logger. Default is 'default'.
     *                        Note: This parameter is not used in the current implementation.
     *
     * @return string An HTML-formatted string representing the log message and context.
     */
    protected function getHtmlMessage(
        string $level, 
        string $message, 
        array $context = []
    ): string 
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
            $newKey = str_replace(' ', '', $key);
            if (is_array($value)) {
                $value = $this->normalizeArrayKeys($value);
            }
            
            $normalized[$newKey] = $value;
        }
    
        return $normalized;
    }

    /**
     * Sends an HTTP request asynchronously using a Fiber.
     *
     * This method sends a POST request to a specified URL with the given body. It handles
     * the request asynchronously and manages potential errors, logging them appropriately.
     *
     * @param string $from    The identifier of the source sending the request (e.g., 'Remote Server', 'Telegram').
     * @param string $url     The URL to which the HTTP request will be sent.
     * @param array<string,mixed>  $body    The body of the HTTP request to be sent.
     * @param string $message The original log message that triggered this request.
     * @param array<string,mixed>  $context Additional context information for logging purposes (optional).
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
        $fiber = new Fiber(function () use ($from, $url, $body, $message, $context) {
            $error = null;
            try {
                $response = (new Network())->post($url, ['body' => $body]);
                if ($response->getStatusCode() !== 200) {
                    $this->write($this->level, 
                        sprintf(
                            'Failed to send log message to %s: %s | Response: %s', 
                            $from,
                            "({$this->level}) {$message}", 
                            $response->getContents()
                        ),
                        $context
                    );
                }
                return;
            } catch (AppException $e) {
                $error = $e->getMessage();
                $from = "{$from} Network Error";
            } catch (Throwable $fe) {
                $error = $fe->getMessage();
                $from = "Unexpected {$from} Error";
            }

            $this->e($from, $error, "({$this->level}) {$message}", $context);
        });

        $fiber->start();
    }

    /**
     * Logs an exception error message with original log information.
     *
     * @param string $from     The source or context where the error originated.
     * @param string $error    The error message or description.
     * @param string $message  The original message that was being logged when the error occurred (optional).
     * @param array  $context  Additional contextual data related to the original log attempt (optional).
     *
     * @return void
     */
    private function e(string $from, string $error, string $message = '', array $context = []): void
    {
        $this->write(LogLevel::EXCEPTION, sprintf('%s Exception: %s', $from, $error), 
        [
            'originalMessage' => $message,
            'originalContext' => $context,
            'originalLevel' => $this->level,
        ]);
    }
}