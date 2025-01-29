<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Logger;

use \Psr\Log\LoggerInterface;
use \App\Config\Logger;
use \Luminova\Logger\LogLevel;
use \Luminova\Logger\LoggerTrait;
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

class NovaLogger implements LoggerInterface
{
    /**
     * Default log path.
     * 
     * @var string|null $path
     */
    protected static ?string $path = null;

    /**
     * The maximum log size in bytes.
     * 
     * @var int|null $maxSize
     */
    protected static ?int $maxSize = null;

    /**
     * Flag indicating if backup should be created.
     * 
     * @var bool|null $autoBackup
     */
    protected static ?bool $autoBackup = null;

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

    use LoggerTrait;

    /**
     * Initialize NovaLogger
     * 
     * @param string $extension log file dot file extension
     */
    public function __construct(protected string $extension = '.log' )
    {
        self::$path ??= root('/writeable/logs/');
        self::$maxSize ??=  (int) env('logger.max.size', Logger::$maxSize);
        self::$autoBackup ??= env('logger.create.backup', Logger::$autoBackup);
    }

    /**
     * Sets the log level for the remote and email logging.
     *
     * @param string $level The log level to set (e.g., `LogLevel::*`, 'error', 'info', 'debug').
     *
     * @return self Returns the current NovaLogger instance.
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
     * @param array $context Optional additional context to include in log.
     *
     * @return void
     * @throws FileException — If unable to write log to file.
     * @throws InvalidArgumentException If an invalid log level is specified.
     */
    public function log($level, $message, array $context = []): void
    {
        LogLevel::assert($level, __METHOD__);
        if(!Logger::$asyncLogging){
            $this->write($level, $message, $context, true);
            return;
        }

        $fiber = new Fiber(function () use ($level, $message, $context) {
            try {
                $this->write($level, $message, $context, true);
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
     * Writes a log message to the specified file.
     *
     * @param string $level   The log level (e.g., 'info', 'error', 'debug').
     * @param string $message The primary log message.
     * @param array  $context Optional associative array providing context data.
     * @param bool  $auth_backup Weather to automatically create backup if max size is reached (default: false).
     *
     * @return bool Returns true if the log written to file, false otherwise.
     * @throws FileException — If unable to write log to file.
     */
    public function write(string $level, string $message, array $context = [], bool $auth_backup = false): bool
    {
        if(make_dir(self::$path)){
            $level = LogLevel::LEVELS[$level] ?? LogLevel::INFO;
            $path = self::$path . "{$level}{$this->extension}";
            $message = self::message($level, $message, $context);

            if(FileManager::write($path, $message . PHP_EOL, FILE_APPEND|LOCK_EX)){
                if($auth_backup){
                    $this->backup($path, $level);
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Logs message to an email asynchronously.
     *
     * @param string $email The recipient email address.
     * @param string $message The message to log.
     * @param array $context Additional context data (optional).
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

        $body = $this->getHtmlMessage($this->level, $message, $context);
        $subject = sprintf('%s (v%.1f) - [%s] Message Log', APP_NAME, APP_VERSION, strtoupper($this->level));
        $fiber = new Fiber(function () use ($email, $subject, $body, $message, $context) {
            try {
                if (!Mailer::to($email)->subject($subject)->body($body)->text(strip_tags($body))->send()) {
                    $this->write(
                        $this->level, 
                        "Failed to send email log: {$message}", 
                        $context, 
                        true
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
     * @param string $url_endpoint The URL to which the log should be sent.
     * @param string $message The message to log.
     * @param array $context Additional context data (optional).
     *
     * @return void
     * > Note: If error occurs during network log, file logger will be used instead.
     * > If exception occurs during network log, file logger with level `exception` be used.
     */
    public function remote(string $url_endpoint, string $message, array $context = []): void 
    {
        if(!$url_endpoint || !Func::isUrl($url_endpoint)){
            $this->log(LogLevel::CRITICAL, sprintf('Invalid network logger URL endpoint: %s', $url_endpoint), [
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
            'details'    => self::message($this->level, $message),
            'context'    => $context,
            'level'      => $this->level,
            'version'    => APP_VERSION,
            'url'        => self::$request->getUrl(),
            'method'     => self::$request->getMethod(),
            'userAgent'  => self::$request->getUserAgent()->toString(),
        ];

        $fiber = new Fiber(function () use ($url_endpoint, $payload, $message) {
            try {
                $response = (new Network())->post($url_endpoint, ['body' => $payload]);
                if ($response->getStatusCode() !== 200) {
                    $this->write($this->level, 
                        sprintf(
                            'Failed to send error to remote server: %s | Response: %s', 
                            $payload['details'], 
                            $response->getContents()
                        ), 
                        $payload['context'],
                        true
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
        return FileManager::write(self::$path . "{$level}{$this->extension}", '', LOCK_EX);
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
    public static function message(
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

        $message = sprintf('[%s] [%s]: %s', strtoupper($level), Time::now()->format('Y-m-d\TH:i:s.uP'), $message);
        $formatted = ($context === []) ? '' : print_r($context, true);

        return $formatted 
            ? sprintf('%s [CONTEXT] %s', $message, $formatted) 
            : $message;
    }

    /**
     * Creates a backup of the log file if it exceeds a specified maximum size.
     *
     * @param string $filepath The path to the current log file.
     * @param string $level    The log level, used in the backup file's naming convention.
     *
     * @return void 
     */
    protected function backup(string $filepath, string $level): void 
    {
        if(self::$maxSize && FileManager::size($filepath) >= (int) self::$maxSize){
            if(self::$autoBackup){
                $backup_time = Time::now()->format('Ymd_His');
                $backup = self::$path . 'backups' . DIRECTORY_SEPARATOR;
                
                if(make_dir($backup)){
                    $backup .= "{$level}_v" . str_replace('.', '_', APP_VERSION) . "_{$backup_time}.zip";

                    try{
                        if(Archive::zip($backup, $filepath)){
                           $this->clear($level);
                        }
                    }catch(FileException $e){
                        FileManager::write(
                            $filepath, 
                            self::message($level, 'Failed to create backup: ' . $e->getMessage()) . PHP_EOL, 
                            FILE_APPEND|LOCK_EX
                        );
                    }
                }
                return;
            }
            
            $this->clear($level);
        }
    }

    /**
     * Generates the HTML message body for log.
     * 
     * @param string $level The log level (e.g., 'info', 'error', 'debug').
     * @param string $message The log message.
     * @param array $context Additional context data (optional).
     * 
     * @return string Return formatted HTML email message body.
     */
    public function getHtmlMessage(string $level, string $message, array $context = []): string 
    {
        self::$request ??= new Request();
        $template = Logger::getEmailLogTemplate(
            self::$request, 
            $message,
            $level,
            $context
        );
        
        return $template ? $template : self::message($level, $message, $context, true);
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