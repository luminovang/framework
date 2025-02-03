<?php 
/**
 * Luminova Framework Default application psr logger class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
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
     * @param array<string|int,mixed> $context Optional additional context to include in log.
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
     * @param array<string|int,mixed> $context Additional context data (optional).
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
     * @param array<string|int,mixed> $context Additional context data (optional).
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
     * @param array<string|int,mixed> $context Additional context data (optional).
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
     * @param array<string|int,mixed> $context Additional context data (optional).
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

        $body = $this->emailTemplate($this->level, $message, $context);
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
                    'Mailer', $e->getMessage(), 
                    $message, $context
                );
            } catch (Throwable $fe) {
                $this->e(
                    'Fiber', $fe->getMessage(), 
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
     * @param array<string|int,mixed> $context Additional context data (optional).
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
            'details'    => $this->message($this->level, $message),
            'context'    => $context,
            'level'      => $this->level,
            'name'       => $this->name,
            'version'    => APP_VERSION,
            'url'        => self::$request->getUrl(),
            'method'     => self::$request->getMethod(),
            'userAgent'  => self::$request->getUserAgent()->toString(),
        ];

        $fiber = new Fiber(function () use ($url, $payload, $message) {
            try {
                $response = (new Network())->post($url, ['body' => $payload]);
                if ($response->getStatusCode() !== 200) {
                    $this->write($this->level, 
                        sprintf(
                            'Failed to send error to remote server: %s | Response: %s', 
                            $payload['details'], 
                            $response->getContents()
                        ), 
                        $payload['context']
                    );
                }
            } catch (AppException $e) {
                $this->e(
                    'Network', $e->getMessage(), 
                    $message, $payload['context']
                );
            } catch (Throwable $fe) {
                $this->e(
                    'Unexpected', $fe->getMessage(), 
                    $message, $payload['context']
                );
            }
        });

        $fiber->start();
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
     * Construct a log message and format timestamp to ISO 8601 with microseconds
     * with the given level, message, and optional context.
     * 
     * @param string $level The log level (e.g., 'INFO', 'ERROR').
     * @param string $message The primary log message.
     * @param array<string,mixed> $context Optional associative array providing context data.
     * @param bool $htmlContext If true the message and context will be formatted as HTML (default: false).
     *
     * @return string Return the formatted log message.
     */
    public function message(
        string $level, 
        string $message, 
        array $context = [],
        bool $htmlContext = false
    ): string
    {
        if($htmlContext){
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

        $message = sprintf(
            '[%s] [%s] [%s]: %s', 
            strtoupper($level), 
            $this->name,
            Time::now()->format('Y-m-d\TH:i:s.uP'), 
            $message
        );

        $formatted = ($context === []) ? '' : print_r($context, true);

        return $formatted 
            ? sprintf('%s [CONTEXT] %s', $message, $formatted) 
            : $message;
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

            $backup_time = Time::now()->format('Ymd_His');
            $backup = self::$path . 'backups' . DIRECTORY_SEPARATOR;
            
            if(make_dir($backup)){
                $backup .= "{$level}_v" . str_replace('.', '_', APP_VERSION) . "_{$backup_time}.zip";

                try{
                    if(Archive::zip($backup, $filepath)){
                        return $this->clear($level);
                    }
                }catch(FileException $e){
                    FileManager::write(
                        $filepath, 
                        $this->message(
                            $level, 
                            sprintf('Failed to create backup for %s: error: %s', $level, $e->getMessage() . PHP_EOL)
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
    protected function emailTemplate(string $level, string $message, array $context = []): string 
    {
        self::$request ??= new Request();
        $template = Logger::getEmailLogTemplate(
            self::$request,
            $this,
            $message,
            $level,
            $context
        );
        
        return $template ? $template : $this->message($level, $message, $context, true);
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
    protected function write(string $level, string $message, array $context = []): bool
    {
        if(make_dir(self::$path)){
            if($level === LogLevel::METRICS){
                return $this->logMetric($message, $context['key'] ?? '');
            }

            $level = LogLevel::LEVELS[$level] ?? LogLevel::INFO;
            $path = self::$path . "{$level}{$this->extension}";
            $message = $this->message($level, $message, $context);

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
    protected function logMetric(string $data, string $key): bool 
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

        $updated = json_encode($contents, JSON_PRETTY_PRINT);
        if ($updated === false) {
            return false;
        }

        return FileManager::write($path, $updated, LOCK_EX);
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
        ], true);
    }
}