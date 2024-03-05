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

use \Luminova\Email\Helpers\Helper;
use \Luminova\Email\Clients\MailClientInterface;
use \Luminova\Email\Exceptions\MailerException;

class NovaMailer implements MailClientInterface
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
     * @var string $Port 
    */
    public string $Port = '';

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
     * Constructor.
     *
     * @param bool $exceptions Should we throw external exceptions?
     */
    public function __construct(bool $exceptions = false)
    {
        $this->exceptions = $exceptions;
    }

    public function initialize(): void
    {
    }
    /**
     * Add an email address to the recipient list.
     *
     * @param string $address The email address.
     * @param string $name    The recipient's name (optional).
     *
     * @return bool True if the address was added successfully, false otherwise.
     */
    public function addAddress(string $address, string $name = ''): bool
    {
        //$name = trim(preg_replace('/[\r\n]+/', '', $name));
        //$recipient = ($name !== '') ? "$name <$address>" : $address;

        $this->to = $address;

        return true;
    }

    /**
     * Add an email address to the recipient list.
     *
     * @param string $address The email address.
     * @param string $name    The recipient's name (optional).
     *
     * @return bool True if the address was added successfully, false otherwise.
     */
    public function addCC(string $address, string $name = ''): bool
    {
        $name = trim(preg_replace('/[\r\n]+/', '', $name));
        $recipient = ($name !== '') ? "$name <$address>" : $address;
        
        $this->cc[] = $recipient;

        return true;
    }

     /**
     * Add an email address to the recipient list.
     *
     * @param string $address The email address.
     * @param string $name    The recipient's name (optional).
     *
     * @return bool True if the address was added successfully, false otherwise.
     */
    public function addBCC(string $address, string $name = ''): bool
    {
        $name = trim(preg_replace('/[\r\n]+/', '', $name));
        $recipient = ($name !== '') ? "$name <$address>" : $address;

        $this->bcc[] = $recipient;
    
        return true;
    }

    /**
     * Add a reply-to address.
     *
     * @param string $address The email address.
     * @param string $name    The recipient's name (optional).
     *
     * @return bool True if the reply-to address was added successfully, false otherwise.
     */
    public function addReplyTo($address, $name = ''): bool 
    {
        $name = trim(preg_replace('/[\r\n]+/', '', $name));
        $recipient = ($name !== '') ? "$name <$address>" : $address;
    

        $this->replyTo = "Reply-To: {$recipient}\r\n";

        return true;
    }

    /**
     * Set the email sender's address.
     *
     * @param string $address The email address.
     * @param string $name    The sender's name (optional).
     * @param bool   $auto    Whether to automatically add the sender's name (optional).
     *
     * @return bool True if the sender's address was set successfully, false otherwise.
     */
    public function setFrom(string $address, string $name = '', bool $auto = true): bool 
    {
        $name = trim(preg_replace('/[\r\n]+/', '', $name));
        $recipient = ($name !== '') ? "$name <$address>" : $address;

        $this->from = "From: {$recipient}\r\n";

        return true;
    }

     /**
     * Add an attachment from a path on the filesystem.
     * Never use a user-supplied path to a file!
     * Returns false if the file could not be found or read.
     * Explicitly *does not* support passing URLs; PHPMailer is not an HTTP client.
     * If you need to do that, fetch the resource yourself and pass it in via a local file or string.
     *
     * @param string $path        Path to the attachment
     * @param string $name        Overrides the attachment name
     * @param string $encoding    File encoding (see $Encoding)
     * @param string $type        MIME type, e.g. `image/jpeg`; determined automatically from $path if not specified
     * @param string $disposition Disposition to use
     *
     * @throws Exception
     *
     * @return bool
     */
    public function addAttachment(
        string $path, 
        string $name = '', 
        string $encoding = self::ENCODING_BASE64, 
        string $type = '', 
        string $disposition = 'attachment'
    ): bool {
        try {
            if (!Helper::fileIsAccessible($path)) {
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

            return false;
        }

        return true;
    }

    /**
     * Send the email.
     *
     * @return bool True if the email was sent successfully, false otherwise.
    */
    public function send(): bool 
    {
        if($this->attachments === []){
            $result = $this->sendWithOutAttachment();
        }else{
            $result = $this->sendWithAttachment();
        }

        if($this->sendWith === 'smtp'){
            $success = $this->smtp_mail($result);
        }else{
            $success = mail($this->to, $this->Subject, $result['body'], $result['headers']);

            if (!$success && $this->exceptions) {
                $error = error_get_last()['message'];
                throw new MailerException($error);
            }
        }
        return $success;
    }

    /**
     * Send email using smtp details 
     * 
     * @return bool
     * @throws MailerException
    */
    private function smtp_mail(array $result): bool
    {
        $from = $this->from ?? $this->Username;

        $smtpConnection = fsockopen($this->Host, $this->Port, $errno, $errstr, 30);

        if (!$smtpConnection) {
            throw new MailerException("Failed to connect to SMTP server: $errstr ($errno)", $errno);
        }

        $this->smtpGet($smtpConnection);

        fputs($smtpConnection, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
        $this->smtpGet($smtpConnection);

        if($this->SMTPAuth){
            fputs($smtpConnection, "AUTH LOGIN\r\n");
            $this->smtpGet($smtpConnection);
            
            fputs($smtpConnection, base64_encode($this->Username) . "\r\n");
            $this->smtpGet($smtpConnection);

            fputs($smtpConnection, base64_encode($this->Password) . "\r\n");
            $this->smtpGet($smtpConnection);
        }

        fputs($smtpConnection, "MAIL FROM: <{$from}>\r\n");
        $this->smtpGet($smtpConnection);

        fputs($smtpConnection, "RCPT TO: <{$this->to}>\r\n");
        $this->smtpGet($smtpConnection);

        fputs($smtpConnection, "DATA\r\n");
        $this->smtpGet($smtpConnection);

        if($this->attachments === []){
            fputs($smtpConnection, $result['headers'] . $result['body'] . "\r\n.\r\n");
        }else{
            fputs($smtpConnection, $result['headers'] . $result['body'] . ".\r\n");
        }
        $this->smtpGet($smtpConnection);

        fputs($smtpConnection, "QUIT\r\n");
        fclose($smtpConnection);

        return true;
    }

    /**
     * @param resource $stream
     * 
     * @return void 
     * @throws MailerException
    */
    private function smtpGet($connection, string $name = ''):void 
    {
        while ($str = fgets($connection, 515)) {
            logger('debug', (string) $str);
            if (substr($str, 3, 1) == " ") {
                break;
            }

            if ($str === false) {
                throw new MailerException("Failed to read from SMTP connection {$name}");
            }
        }
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
        $headers .= "Message-ID: <". md5(uniqid(time())) . "@" . $_SERVER['SERVER_NAME'].">\r\n";

        return $headers;
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
            $fileContent = file_get_contents($attachment['path']);
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
     * Send messages using SMTP.
     * 
     * @return void
     */
    public function isSMTP(): void 
    {
        $this->sendWith = 'smtp';
    }

    /**
     * Send messages using PHP's mail() function.
     * 
     * @return void
     */
    public function isMail(): void 
    {
        $this->sendWith = 'mail';
    }

    /**
     * Sets message type to HTML or plain.
     *
     * @param bool $isHtml True for HTML mode
     * 
     * @return void
     */
    public function isHTML(bool $isHtml = true): void 
    {
        if ($isHtml) {
            $this->contentType = static::CONTENT_TYPE_TEXT_HTML;
        } else {
            $this->contentType = static::CONTENT_TYPE_PLAINTEXT;
        }
    }
}