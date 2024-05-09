<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Email;

use \Luminova\Interface\MailerInterface;
use \Luminova\Application\Foundation;
use \Luminova\Base\BaseMailer;
use \App\Controllers\Config\Preference;
use \Luminova\Exceptions\MailerException;
use \Exception;

class Mailer
{
    /**
     * Mailer singleton instance
     * 
     * @var self $mailer
    */
    private static ?self $instance = null;

    /**
     * Mail client instance.
     * 
     * @var MailerInterface $client
    */
    private ?MailerInterface $client = null;

    /**
     * @var string $from 
    */
    private string $from = '';

    /**
     * @var string $fromName 
    */
    private string $fromName = '';

    /**
     * @var bool $fromAuto 
    */
    private bool $fromAuto = true;

    /**
     * Mailer constructor.
     *
     * @param MailerInterface|string|null $interface The mailer client interface.
     * 
     * @throws MailerException Throws if mail client doesn't implement MailerInterface.
     */
    private function __construct(MailerInterface|string|null $interface = null)
    {
        $interface ??= Preference::getMailer();

        if(is_string($interface) && class_exists($interface)) {
            $interface = new $interface(!PRODUCTION);
        }

        if (!$interface instanceof MailerInterface) {
            throw MailerException::throwWith('invalid_client', get_class($interface));
        }

        if($interface === null){
            throw MailerException::throwWith('no_client', $interface);
        }

        $this->client = $interface;
        $this->initialize();
    }

    /**
     * Get the Mailer client instance.
     * 
     * @return MailerInterface The Mailer client instance.
    */
    public function getClient(): ?MailerInterface
    {
        return $this->client;
    }

    /**
     * Send email to a single address.
     *
     * @param string $address Email address to send email to.
     * 
     * @return static Return new static mailer class instance.
     * @throws MailerException Throws if error occurred while sending email.
    */
    public static function to(string $address): static
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        static::$instance->address($address);

        return static::$instance;
    }

    /**
     * Add an email address to the recipient list.
     *
     * @param string $address The email address.
     * @param string $name    The recipient's name (optional).
     *
     * @return self Return the Mailer class instance.
    */
    public function address(string $address, string $name = ''): self
    {
        $this->client->addAddress($address, $name);

        return $this;
    }

    /**
     * Add a reply-to address.
     *
     * @param string $address The email address.
     * @param string $name    The recipient's name (optional).
     *
     * @return self Return the Mailer class instance.
     */
    public function replyTo($address, $name = ''): self
    {
        $this->client->addReplyTo($address, $name);

        return $this;
    }

     /**
     * Add an email address to the recipient list.
     *
     * @param string $address The email address.
     * @param string $name    The recipient's name (optional).
     *
     * @return self Return the Mailer class instance.
     */
    public function cc(string $address, string $name = ''): self
    {
        $this->client->addCC($address, $name);

        return $this;
    }

    /**
     * Add an email address to the recipient list.
     *
     * @param string $address The email address.
     * @param string $name    The recipient's name (optional).
     *
     * @return self Return the Mailer class instance.
     */
    public function bcc(string $address, string $name = ''): self
    {
        $this->client->addBCC($address, $name);

        return $this;
    }

    /**
     * Set the email sender's address.
     *
     * @param string $address The email address.
     * @param string $name    The sender's name (optional).
     * @param bool   $auto    Whether to automatically add the sender's name (optional).
     *
     * @return self Return the Mailer class instance.
     */
    public function from(string $address, string $name = '', bool $auto = true): self
    {
        $this->from = $address;
        $this->fromName = $name;
        $this->fromAuto = $auto;

        return $this;
    }

    /**
     * Sets the body of the email message.
     *
     * @param BaseMailer|string $message The body content of the email.
     * 
     * @return self Return the Mailer class instance.
    */
    public function body(BaseMailer|string $message): self 
    {
        if($message instanceof BaseMailer){
            $this->client->Subject = $message->getSubject() ?? '';
            $this->client->AltBody = $message->getText() ?? '';
            $this->client->Body = $message->getHtml() ?? $message->getText() ?? '';

            if(($attachemts = $message->getFiles()) && $attachemts !== []){
                foreach($attachemts as $attach){
                    $this->client->addAttachment(
                        $attach['path'], 
                        $attach['name'] ?? '', 
                        $attach['encoding'] ?? 'base64', 
                        $attach['type'] ?? '', 
                        $attach['disposition'] ?? 'attachment'
                    );
                }
            }

            return $this;
        }

        $this->client->Body = $message;

        return $this;
    }

    /**
     * Sets the alternative body of the email message.
     *
     * @param string $message The alternative body content of the email.
     * 
     * @return self Return the Mailer class instance.
    */
    public function text(string $message): self 
    {
        $this->client->AltBody = $message;

        return $this;
    }

    /**
     * Sets the subject of the email message.
     *
     * @param string $subject The subject of the email.
     * 
     * @return self Return the Mailer class instance.
    */
    public function subject(string $subject): self 
    {
        $this->client->Subject = $subject;

        return $this;
    }

    /**
     * Add an attachment from a path on the filesystem.
     *
     * @param string $path        Path to the attachment
     * @param string $name        Overrides the attachment name
     * @param string $encoding    File encoding (see $Encoding)
     * @param string $type        MIME type, e.g. `image/jpeg`; determined automatically from $path if not specified
     * @param string $disposition Disposition to use
     *
     * @return self Return the Mailer class instance.
     * @throws MailerException Throws if file could not be read.
     */
    public function addFile(
        string $path, 
        string $name = '', 
        string $encoding = 'base64', 
        string $type = '', 
        string $disposition = 'attachment'
    ): self {
        try{
            $this->client->addAttachment($path, $name, $encoding, $type, $disposition);
        }catch(Exception | MailerException $e){
            throw new MailerException($e->getMessage(), $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * Send the email.
     * 
     * @param BaseMailer|string|null $message Optionally pass message body in send method.
     * 
     * @return bool Return true if the email was sent successfully, false otherwise.
     * @throws MailerException Throws if error occurred while sending email.
     */
    public function send(BaseMailer|string|null $message = null): bool
    {
        if($message !== null){
            $this->body($message);
        }

        try{
            $this->client->setFrom($this->from, $this->fromName, $this->fromAuto);

            return $this->client->send();
        }catch(Exception | MailerException $e){
            if(PRODUCTION){
                logger('exception', $e->getMessage());
                return false;
            }

            throw new MailerException($e->getMessage(), $e->getCode(), $e);
        }

        return false;
    }

    /**
     * Configure the PHPMailer instance.
     * 
     * @return void 
     */
    private function initialize(): void
    {
        $this->client->SMTPDebug = static::debugable() ? 3 : 0;
        $this->client->CharSet = static::getCharset(env('smtp.charset'));
        $this->client->XMailer = Foundation::copyright();

        if ((bool) env('smtp.use.credentials')) {
            $this->client->isSMTP();
            $this->client->Host = env('smtp.host');
            $this->client->Port = env('smtp.port');

            if ((bool) env('smtp.use.password')) {
                $this->client->SMTPAuth = true;
                $this->client->Username = env('smtp.username');
                $this->client->Password = env('smtp.password');
            }

            $this->client->SMTPSecure = static::getEncryption(env('smtp.encryption'));
        } else {
            $this->client->isMail();
        }

        $this->client->isHTML(true);
        $this->client->initialize();

        $this->fromName = APP_NAME;
        $this->from = env('smtp.email.sender', 'test@example.com');
    }

    /**
     * Determine whether debugging is enabled.
     *
     * @return bool True if debugging is enabled, false otherwise.
     */
    private static function debugable(): bool
    {
        return !PRODUCTION && (bool) env('smtp.debug');
    }

    /**
     * Get the encryption type.
     *
     * @param string $encryption The encryption type.
     *
     * @return int The encryption type constant.
     */
    private static function getEncryption(string $encryption): string
    {
        $types = [
            'tls' => 'tls',
            'ssl' => 'ssl'
        ];

        return $types[$encryption] ?? 'tls';
    }

    /**
     * Get the character encoding.
     *
     * @param string $charset The character encoding.
     *
     * @return int The character encoding constant.
     */
    private static function getCharset(string $charset): string
    {
        $types = [
            'utf8' => 'utf-8',
            'iso88591' => 'iso-8859-1',
            'ascii' => 'us-ascii',
        ];

        return $types[$charset] ?? 'utf-8';
    }
}