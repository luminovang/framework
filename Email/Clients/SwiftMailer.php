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
use \Swift_SmtpTransport;
use \Swift_Mailer;
use \Swift_Message;
use \Swift_Attachment;

class SwiftMailer implements MailClientInterface
{

    private array $addresses = [];

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
     * @var string $replyTo 
    */
    private string $replyTo = '';

    /**
     * @var string $sendWith 
    */
    private string $sendWith = '';

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
     * @var Swift_SmtpTransport $transport 
    */
    private ?Swift_SmtpTransport $transport = null;

    /**
     * @var Swift_Mailer $mailer 
    */
    private ?Swift_Mailer $mailer = null;


    /**
     * Constructor.
     *
     * @param bool $exceptions Should we throw external exceptions?
     */
    public function __construct(bool $exceptions = false)
    {
        if(!class_exists('\Swift_Mailer')){
            throw MailerException::throwWith('class_not_exist', 'Swift_Mailer');
        }
    }

    public function initialize(): void
    {
        $this->transport = new Swift_SmtpTransport($this->Host, $this->Port);
        if($this->SMTPAuth){
            $this->transport->setUsername($this->Username);
            $this->transport->setPassword($this->Password);
        }
        $this->mailer = new Swift_Mailer($this->transport);
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
        if($name === ''){
            $this->addresses['from'][] = $address;
        }else{
            $this->addresses['from'][$address] = $name;
        }

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
        if($name === ''){
            $this->addresses['cc'][] = $address;
        }else{
            $this->addresses['cc'][$address] = $name;
        }

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
        if($name === ''){
            $this->addresses['bcc'][] = $address;
        }else{
            $this->addresses['bcc'][$address] = $name;
        }

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
    public function addAddress(string $address, string $name = ''): bool
    {
        if($name === ''){
            $this->addresses['to'][] = $address;
        }else{
            $this->addresses['to'][$address] = $name;
        }
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
        string $encoding = 'base64', 
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
    private function send(): bool
    {
        $message = new Swift_Message($this->Subject);
        $message->setFrom($this->addresses['from']);
        $message->setTo($this->addresses['to']);
        if(isset($this->addresses['cc'])){
            $message->setCc($this->addresses['cc']);
        }
        if(isset($this->addresses['bcc'])){
            $message->setBcc($this->addresses['bcc']);
        }
        if($this->replyTo){
            $message->setReplyTo($this->replyTo);
        }
        if($this->AltBody){
            $message->addPart($this->AltBody, 'text/html');
        }
        $message->setBody($this->Body);
        if($this->attachments !== []){
            foreach($this->attachments as $attach){
                $message->attach(Swift_Attachment::fromPath($attach['path']));
            }
        }

        $result = $this->mailer->send($message);

        return $result > 0;
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
       
    }
}
