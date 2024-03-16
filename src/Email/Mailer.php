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

use Exception;
use \Luminova\Email\Clients\MailClientInterface;
use \Luminova\Email\Clients\NovaMailer;
use \Luminova\Email\Clients\PHPMailer;
use \Luminova\Base\BaseConfig;
use \Luminova\Email\Exceptions\MailerException;

class Mailer
{
    /**
     * Mailer singleton instance
     * 
     * @var MailClientInterface $mailer
    */
    private static ?self $instance = null;

    /**
     * MailClientInterface instance
     * 
     * @var object $client
    */
    private static object $client;

    /**
     * Message subject
     * 
     * @var string $Subject
    */
    public string $Subject = '';

    /**
     * Message body
     * 
     * @var string $Body
    */
    public string $Body = '';

    /**
     * Alternative message body
     * 
     * @var string $AltBody 
    */
    public string $AltBody = '';

    /**
     * Available clients 
     * 
     * @var array $clients 
    */
    private static array $clients = [
        PHPMailer::class, 
        NovaMailer::class
    ];


    /**
     * Mailer constructor.
     *
     * @param MailClientInterface|string|null $client The mail client instance or class name.
     * @throws MailerException
     */
    private function __construct(MailClientInterface|string|null $client = null)
    {
        $development = !PRODUCTION;
        try{
            if ($client === null) {
                static::$client = new NovaMailer($development);
            } elseif ($client instanceof MailClientInterface) {
                static::$client = $client;
            } elseif (is_string($client) && in_array($client, static::$clients, true)) {
                static::$client = new $client($development);
            } else {
                throw MailerException::throwWith('invalid_client', $client);
            }
            static::initialize();
        }catch(Exception|MailerException $e) {
            if($development){
                throw $e;
            }
        }
    }

    /**
     * Get the Mailer client instance.
     * 
     * @return static::$client The Mailer client instance.
     */
    public static function getClient(): object
    {
        return static::$client;
    }

    /**
     * Get the Mailer instance.
     *
     * @param MailClientInterface|string|null $client The mail client instance or class name.
     * 
     * @throws MailerException
    */
    public static function getInstance(MailClientInterface|string|null $client = null): self
    {
        if (static::$instance === null) {
            static::$instance = new static($client);
        }

        return static::$instance;
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
        return static::$client->addAddress($address, $name);
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
        return static::$client->addReplyTo($address, $name);
    }

     /**
     * Add an email address to the recipient list.
     *
     * @param string $address The email address.
     * @param string $name    The recipient's name (optional).
     *
     * @return bool True if the address was added successfully, false otherwise.
     */
    public function addCc(string $address, string $name = ''): bool
    {
        return static::$client->addCC($address, $name);
    }

     /**
     * Add an email address to the recipient list.
     *
     * @param string $address The email address.
     * @param string $name    The recipient's name (optional).
     *
     * @return bool True if the address was added successfully, false otherwise.
     */
    public function addBcc(string $address, string $name = ''): bool
    {
        return static::$client->addBCC($address, $name);
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
        return static::$client->setFrom($address, $name, $auto);
    }

   /**
     * Sets the body of the email message.
     *
     * @param string $message The body content of the email.
     */
    public function setBody(string $message): void 
    {
        static::$client->Body = $message;
    }

    /**
     * Sets the alternative body of the email message.
     *
     * @param string $message The alternative body content of the email.
     */
    public function setAltBody(string $message): void 
    {
        static::$client->AltBody = $message;
    }

    /**
     * Sets the subject of the email message.
     *
     * @param string $subject The subject of the email.
     */
    public function setSubject(string $subject): void 
    {
        static::$client->Subject = $subject;
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
    ) {
        return static::$client->addAttachment($path, $name, $encoding, $type, $disposition);
    }

    /**
     * Send the email.
     *
     * @return bool True if the email was sent successfully, false otherwise.
     */
    public function send(): bool
    {
        try{
            return static::$client->send();
        }catch(Exception $e){
            throw new MailerException($e->getMessage(), $e->getCode());
        }
    }

    /**
     * Configure the PHPMailer instance.
     */
    private static function initialize(): void
    {
        static::$client->SMTPDebug = static::shouldDebug() ? 3 : 0;
        static::$client->CharSet = static::getCharset(env("smtp.charset"));
        static::$client->XMailer = BaseConfig::copyright();
        if ((bool) env("smtp.use.credentials")) {
            static::$client->isSMTP();
            static::$client->Host = env("smtp.host");
            static::$client->Port = env("smtp.port");

            if ((bool) env("smtp.use.password")) {
                static::$client->SMTPAuth = true;
                static::$client->Username = env("smtp.username");
                static::$client->Password = env("smtp.password");
            }

            static::$client->SMTPSecure = static::getEncryptionType(env("smtp.encryption"));
        } else {
            static::$client->isMail();
        }

        static::$client->setFrom(env("smtp.email.sender"), APP_NAME);
        static::$client->isHTML(true);
        static::$client->initialize();
    }

    /**
     * Determine whether debugging is enabled.
     *
     * @return bool True if debugging is enabled, false otherwise.
     */
    private static function shouldDebug(): bool
    {
        return !PRODUCTION && (bool) env("smtp.debug");
    }

    /**
     * Get the encryption type.
     *
     * @param string $encryption The encryption type.
     *
     * @return int The encryption type constant.
     */
    private static function getEncryptionType(string $encryption): string
    {
        $types = [
            "tls" => 'tls',
            "ssl" => 'ssl'
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
            "utf8" => 'utf-8',
            "iso88591" => 'iso-8859-1',
            "ascii" => 'us-ascii',
        ];

        return $types[$charset] ?? 'utf-8';
    }
}