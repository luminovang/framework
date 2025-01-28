<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Email\Clients;

use \Luminova\Storages\FileManager;
use \Luminova\Interface\MailerInterface;
use \Luminova\Exceptions\MailerException;
use \Luminova\Logger\Logger;

class NovaMailer implements MailerInterface
{
    public const CONTENT_TYPE_PLAINTEXT = 'text/plain';
    public const CONTENT_TYPE_TEXT_CALENDAR = 'text/calendar';
    public const CONTENT_TYPE_TEXT_HTML = 'text/html';
    public const CONTENT_TYPE_MULTIPART_ALTERNATIVE = 'multipart/alternative';
    public const CONTENT_TYPE_MULTIPART_MIXED = 'multipart/mixed';
    public const CONTENT_TYPE_MULTIPART_RELATED = 'multipart/related';

    public const ENCODING_7BIT = '7bit';
    public const ENCODING_8BIT = '8bit';
    public const ENCODING_BASE64 = 'base64';
    public const ENCODING_BINARY = 'binary';
    public const ENCODING_QUOTED_PRINTABLE = 'quoted-printable';

    /**
     * @var string $SMTPSecure 
     */
    private string $to = '';

    /**
     * @var array $bcc 
     */
    private array $bcc = [];

    /**
     * @var array $cc 
     */
    private array $cc = [];

    /**
     * @var string $Subject 
     */
    public string $Subject = '';

    /**
     * @var string $Body 
     */
    public string $Body = '';

    /**
     * @var string $AltBody 
     */
    public string $AltBody = '';

    /**
     * @var string $from 
     */
    private string $from = '';

    /**
     * @var string $replyTo 
     */
    private string $replyTo = '';

    /**
     * @var string $sendWith 
     */
    private string $sendWith = '';

    /**
     * @var string $contentType 
     */
    private string $contentType = 'text/plain';

    /**
     * @var array $attachments 
     */
    private array $attachments = [];

    /**
     * @var string $XMailer 
    */
    public string $XMailer = '';

    /**
     * @var string $CharSet 
     */
    public string $CharSet = '';

    /**
     * @var int $SMTPDebug 
     */
    public int $SMTPDebug = 0;

    /**
     * @var bool $exceptions 
     */
    public bool $exceptions = false;

    /**
     * @var string $Host 
     */
    public string $Host = '';

    /**
     * @var int $Port 
     */
    public int $Port = -1;

    /**
     * @var bool $SMTPAuth 
     */
    public bool $SMTPAuth = false;

    /**
     * @var string $Username 
     */
    public string $Username = '';

    /**
     * @var string $Password 
     */
    public string $Password = '';

    /**
     * @var string $SMTPSecure 
     */
    public string $SMTPSecure = 'tls';

    /**
     * @var mixed $connection
     */
    private mixed $connection = false;

    /**
     * {@inheritdoc}
     */
    public function __construct(bool $exceptions = false)
    {
        $this->exceptions = $exceptions;
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(): void
    {

    }

    /**
     * {@inheritdoc}
     */
    public function addAddress(string $address, string $name = ''): bool
    {
        $this->to = $address;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function addCC(string $address, string $name = ''): bool
    {
        $name = trim(preg_replace('/[\r\n]+/', '', $name));
        $recipient = ($name !== '') ? "$name <$address>" : $address;
        
        $this->cc[] = $recipient;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function addBCC(string $address, string $name = ''): bool
    {
        $name = trim(preg_replace('/[\r\n]+/', '', $name));
        $recipient = ($name !== '') ? "$name <$address>" : $address;

        $this->bcc[] = $recipient;
    
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function setFrom(string $address, string $name = '', bool $auto = true): bool 
    {
        $name = trim(preg_replace('/[\r\n]+/', '', $name));
        $recipient = ($name !== '') ? "$name <$address>" : $address;

        $this->from = "From: {$recipient}\r\n";

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function addAttachment(
        string $path, 
        string $name = '', 
        string $encoding = self::ENCODING_BASE64, 
        string $type = '', 
        string $disposition = 'attachment'
    ): bool {
        try {
            if (!FileManager::isAccessible($path)) {
                throw MailerException::throwWith('file_access', $path);
            }

            if ('' === $name) {
                $name = basename($path);
            }

            $this->attachments[] = [
                'path' => $path,
                'name' => $name,
                'encoding' => $encoding,
                'type' => $type,
                'disposition' => $disposition
            ];
        } catch (MailerException $e) {
            if ($this->exceptions) {
                throw MailerException::throwWith($e->getMessage());
            }
            Logger::dispatch('exception', $e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function addReplyTo(string $address, string $name = ''): bool 
    {
        $name = trim(preg_replace('/[\r\n]+/', '', $name));
        $recipient = ($name !== '') ? "$name <$address>" : $address;
    

        $this->replyTo = "Reply-To: {$recipient}\r\n";

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function send(): bool 
    {
        error_clear_last();

        $result = ($this->attachments === []) ? $this->sendWithOutAttachment() : $this->sendWithAttachment();
        $success = ($this->sendWith === 'smtp') ?  $this->smtp_mail($result) : mail($this->to, $this->Subject, $result['body'], $result['headers']);

        if (!$success) {
            $error = error_get_last();
            if($error !== null){
                throw new MailerException($error['message']);
            }

            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isSMTP(): void 
    {
        $this->sendWith = 'smtp';
    }

    /**
     * {@inheritdoc}
     */
    public function isMail(): void 
    {
        $this->sendWith = 'mail';
    }

    /**
     * {@inheritdoc}
     */
    public function isHTML(bool $isHtml = true): void 
    {
        $this->contentType = $isHtml ? self::CONTENT_TYPE_TEXT_HTML : self::CONTENT_TYPE_PLAINTEXT;
    }

    /**
     * Get initial headers.
     *
     * @return string
     */
    private function getHeaders(): string 
    {
        $XMailer = trim($this->XMailer);

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= $this->from;
        $headers .= $this->replyTo;
        if($this->cc !== []){
            $headers .= "Cc: " . implode(',', $this->cc) . "\r\n"; 
        }
        if($this->bcc !== []){
            $headers .= "Bcc:  " . implode(',', $this->bcc) . "\r\n";
        }

        $headers .= "X-Mailer: {$XMailer}\r\n";
        $headers .= "Date: ".date("r (T)")."\r\n";
    
        return $headers . ("Message-ID: <". md5(uniqid((string) time())) . "@" . $_SERVER['SERVER_NAME'].">\r\n");
    }

    /**
     * Send the email.
     *
     * @return array
     */
    private function sendWithOutAttachment(): array 
    {
        $headers = $this->getHeaders();
        $headers .= "Content-Type: {$this->contentType}; charset=UTF-8\r\n";
        return [
            'body' => $this->Body,
            'headers' => $headers
        ];
    }

    /**
     * Send the email.
     * 
     * @return array
     */
    private function sendWithAttachment(): array 
    {
        $boundary = uniqid('np');
        $headers = $this->getHeaders();
        $headers .= "Content-Type: multipart/mixed; boundary=$boundary\r\n";

        $body = "--$boundary\r\n";
        $body .= "Content-Type: {$this->contentType}; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n";
        $body .= "\r\n";
        $body .= $this->Body . "\r\n";

        foreach ($this->attachments as $attachment) {
            $fileContent = get_content($attachment['path']);
            $fileContent = chunk_split(base64_encode($fileContent));
            $body .= "--$boundary\r\n";
            $body .= "Content-Type: application/octet-stream; name=\"" . $attachment['name'] . "\"\r\n";
            $body .= "Content-Transfer-Encoding: {$attachment['encoding']}\r\n";
            $body .= "Content-Disposition: {$attachment['disposition']}\r\n";
            $body .= "\r\n";
            $body .= $fileContent . "\r\n";
        }

        $body .= "--$boundary--";

        return [
            'body' => $body,
            'headers' => $headers
        ];
    }

    /**
     * Send email using smtp details 
     * 
     * @param array $result Email content information.
     * 
     * @return bool Return true if successful, otherwise false.
     * @throws MailerException
     */
    private function smtp_mail(array $result): bool
    {
        $from = $this->from ?? $this->Username;
        $options = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ];

        $this->connection = $this->connection($options);
        $this->smtpGet();

        fwrite($this->connection, "EHLO " . APP_HOSTNAME . "\r\n");
       // fwrite($this->connection, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
        $this->smtpGet();

        if($this->SMTPAuth){
            fwrite($this->connection, "AUTH LOGIN\r\n");
            $this->smtpGet();
            
            fwrite($this->connection, base64_encode($this->Username) . "\r\n");
            $this->smtpGet();

            fwrite($this->connection, base64_encode($this->Password) . "\r\n");
            $this->smtpGet();
        }

        fwrite($this->connection, "MAIL FROM: <{$from}>\r\n");
        $this->smtpGet();

        fwrite($this->connection, "RCPT TO: <{$this->to}>\r\n");
        $this->smtpGet();

        fwrite($this->connection, "DATA\r\n");
        $this->smtpGet();

        $end = ($this->attachments === []) ? "\r\n.\r\n" : ".\r\n";
        fwrite($this->connection, $result['headers'] . $result['body'] . $end);
        $this->smtpGet();

        fwrite($this->connection, "QUIT\r\n");

        return true;
    }

    /**
     * Open socket connection
     * 
     * @param array $options
     * 
     * @return mixed Return resource.
     */
    public function connection(array $options = []): mixed
    {
        if ($this->connected()) {
            return $this->connection;
        }

        $transport = $this->SMTPSecure . '://' . $this->Host;

        if(function_exists('stream_socket_client')){
            $transport .= ':' . $this->Port;
        
            $context = stream_context_create($options);
            $conn = stream_socket_client(
                $transport,
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT,
                $context
            );
        }else{
            $conn = fsockopen($transport, $this->Port, $errno, $errstr, 30);
        }

        if (!is_resource($conn)) {
            throw new MailerException("Failed to connect to SMTP server: $errstr ($errno)", $errno);
        }

        return $conn;
    }

    /**
     * Check socket already connected.
     *
     * @return bool Return true if connected
     */
    public function connected(): bool
    {
        if (is_resource($this->connection)) {
            $status = stream_get_meta_data($this->connection);
            if ($status['eof']) {
                Logger::dispatch('debug', 'SMTP NOTICE: EOF caught while checking if connected');
                $this->close();

                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Close the socket connection.
     */
    public function close(): void
    {
        if (is_resource($this->connection)) {
            fclose($this->connection);
            $this->connection = null;
            Logger::debug('Connection: closed');
        }
    }

    /**
     * Read SMTP connection.
     * 
     * @param string $name Read smtp connection name
     * 
     * @return void 
     * @throws MailerException
     */
    private function smtpGet(string $name = ''): void 
    {
        while ($str = fgets($this->connection, 515)) {
            if (substr($str, 3, 1) == " ") {
                break;
            }

            if ($str === false) {
                $error = "Failed to read from SMTP connection {$name}";
                if($this->exceptions){
                    throw new MailerException($error);
                }

                Logger::dispatch('debug', $error);
            }
        }
    }
}