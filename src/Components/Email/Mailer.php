<?php
/**
 * Luminova Framework Mailer Helper Class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Components\Email;

use \Throwable;
use \Luminova\Luminova;
use \Luminova\Email\Clients\NovaMailer;
use \Luminova\Base\Mailer as MailTemplate;
use \Luminova\Exceptions\{AppException, MailerException};
use \Luminova\Interface\{LazyObjectInterface, MailerInterface};

/**
 * Mailer class for sending emails.
 *
 * @method static $this reset() Clear the mailer state to its default values and return the instance.
 * @method static $this clearAddresses() Clear all primary recipients (To) and return the instance.
 * @method static $this clearCc() Clear all CC recipients and return the instance.
 * @method static $this clearBcc() Clear all BCC recipients and return the instance.
 * @method static $this clearAttachments() Remove all attachments and return the instance.
 * @method static $this clearHeaders() Remove all custom headers and return the instance.
 */
final class Mailer implements LazyObjectInterface
{
    /**
     * Mailer singleton instance
     * 
     * @var self|null $instance
     */
    private static ?self $instance = null;

    /**
     * Mail client instance.
     * 
     * @var MailerInterface $client
     */
    private ?MailerInterface $client = null;

    /**
     * From email address. 
     * 
     * @var string $from 
     */
    private string $from = '';

    /**
     * From name. 
     * 
     * @var string $fromName 
     */
    private string $fromName = '';

    /**
     * From auto email name. 
     * 
     * @var bool $fromAuto 
     */
    private bool $fromAuto = true;

    /**
     * Initialize mailer constructor class.
     *
     * @param MailerInterface|string|null $interface The mailer client interface.
     * 
     * @throws MailerException Throws if mail client doesn't implement MailerInterface.
     */
    public function __construct(MailerInterface|string|null $interface = null)
    {
        $interface ??= Luminova::kernel()->getMailer() 
            ?? new NovaMailer(!PRODUCTION);

        if(is_string($interface)) {
            if(!class_exists($interface)) {
                throw MailerException::rethrow('no_client', $interface);
            }

            $interface = new $interface(!PRODUCTION);
        }

        if (!$interface instanceof MailerInterface) {
            throw MailerException::rethrow('invalid_client', $interface::class);
        }
        
        $this->client = $interface;
        $this->initialize();

        $interface = null;
    }

    /**
     * Retrieve the mail client object.
     * 
     * @param string $method The method to call.
     * @param array $arguments Optional arguments to pass to the method.
     * 
     * @return mixed Return the result of the method.
     * @throws MailerException Throws if an error occurs.
     */
    public function __call(string $method, array $arguments): mixed
    {
        try{
            return $this->client->{$method}(...$arguments);
        }catch(Throwable $e){
            if($e instanceof AppException){
                throw $e;
            }

            throw new MailerException($e->getMessage(), $e->getCode(), $e);
        }

        return null;
    }

    /**
     * Magic method to dynamically set a property in the mail `client` instance.
     *
     * This allows assigning arbitrary properties that are not explicitly declared in the class.
     *
     * @param string $name  The name of the property being set.
     * @param mixed  $value The value to assign to the property.
     * 
     * @return void
     */
    public function __set(string $name, mixed $value): void 
    {
        $this->client->{$name} = $value;
    }

    /**
     * Magic method to retrieve a dynamically set property from the mail `client` instance.
     *
     * If the property is not set, it returns `null` instead of triggering an error.
     *
     * @param string $name The name of the property being accessed.
     * 
     * @return mixed|null Return the value of the property if it exists, otherwise `null`.
     */
    public function __get(string $name): mixed 
    {
        return $this->client->{$name} ?? null;
    }

    /**
     * Magic method to check if a dynamic property exists in the mail `client` instance.
     *
     * This is useful for `isset()` checks to determine whether a property has been set.
     *
     * @param string $name The name of the property to check.
     * 
     * @return bool Return `true` if the property exists, otherwise `false`.
     */
    public function __isset(string $name): bool 
    {
        return isset($this->client->{$name});
    }

    /**
     * Initialize and retrieve the singleton instance of the Mailer class.
     *
     * @param MailerInterface|string|null $interface The mailer client interface to be used for instantiation.
     * 
     * @return Mailer Returns the shared singleton instance of the Mailer class.
     */
    public static function getInstance(MailerInterface|string|null $interface = null): self
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self($interface);
        }

        return self::$instance;
    }

    /**
     * Send email to a single address.
     *
     * @param string $address Email address to send email to.
     * 
     * @return self Return new static mailer class instance.
     * @throws MailerException Throws if error occurred while sending email.
     */
    public static function to(string $address): self
    {
        return (new self())->address($address);
    }

    /**
     * Retrieve the Mailer client instance.
     * 
     * This method returns an instance of the mail client in use.
     * 
     * @return MailerInterface Return he Mailer client instance.
     * @example - Example:
     * 
     * ```php
     * $client = $mailer->getClient() // Luminova mailer client interface
     *      ->getMailer(); // e.g, PHPMailer instance
     * ```
     */
    public function getClient(): ?MailerInterface
    {
        return $this->client;
    }

    /**
     * Set SMTP stream options.
     * 
     * This method allows you to define custom stream context options for the SMTP connection.
     * It can be used to configure SSL/TLS behavior, such as disabling peer verification or allowing self-signed certificates.
     * 
     * @param array $smtpOptions An associative array of stream context options.
     * 
     * @return self Return the Mailer class instance.
     * 
     * @example - Example:
     * ```php
     * $mailer->options([
     *      'ssl' => [
     *           'verify_peer' => false,
     *           'verify_peer_name' => false,
     *           'allow_self_signed' => true,
     *      ]
     * ]);
     * ```
     */
    public function options(array $smtpOptions): self 
    {
        $this->client->SMTPOptions = $smtpOptions;
        return $this;
    }

    /**
     * Set the priority of the email.
     *
     * Maps directly to the `X-Priority` header.  
     * Common values: 1 (High), 3 (Normal), 5 (Low).  
     *
     * @param int $priority Email priority value.
     * 
     * @return self Return the Mailer class instance.
     */
    public function priority(int $priority): self
    {
        $this->client->Priority = $priority;
        return $this;
    }

    /**
     * Set the X-Mailer header.
     *
     * Indicates the software used to send the message.  
     * Useful for identification or tracking by recipients.  
     *
     * @param string $xMailer Name of the mailer software.
     * 
     * @return self Return the Mailer class instance.
     */
    public function xMailer(string $xMailer): self
    {
        $this->client->XMailer = $xMailer;
        return $this;
    }

    /**
     * Set the Organization header.
     *
     * Identifies the sending organization or company.  
     * Some mail clients may display this in the message properties.  
     *
     * @param string $org Organization name.
     * 
     * @return self Return the Mailer class instance.
     */
    public function organization(string $org): self
    {
        $this->client->Organization = $org;
        return $this;
    }

    /**
     * Set the character set of the email body.
     *
     * Controls encoding of message content.  
     * Common values: 'UTF-8', 'ISO-8859-1', etc.  
     *
     * @param string $charset Character set name.
     * 
     * @return self Return the Mailer class instance.
     */
    public function charset(string $charset): self
    {
        $this->client->CharSet = $charset;
        return $this;
    }
   
    /**
     * Set the timeout for the mail client.
     *
     * Controls how long the client waits for a server response before failing.  
     * Timeout is in seconds.  
     *
     * @param int $timeout Timeout duration in seconds.
     * 
     * @return self Return the Mailer class instance.
     */
    public function timeout(int $timeout): self
    {
        $this->client->Timeout = $timeout;
        return $this;
    }

    /**
     * Set the email format to HTML or plain text.
     *
     * This method sets whether the email should be sent as HTML or plain text.
     * By default, it sends HTML. If you want to send a plain text email,
     * pass `false` to the method.
     *
     * @param bool $html Whether the email should be sent as HTML (default is true).
     *
     * @return self Return the Mailer class instance.
     */
    public function isHtml(bool $html = true): self 
    {
        $this->client->isHTML($html);
        return $this;
    }

    /**
     * Set the mail transport method to either Mail or SMTP.
     *
     * This method determines whether to send emails using the PHP `mail()` function 
     * or via SMTP. By default, it uses `mail()`. To send emails via SMTP, pass 
     * `false` to this method.
     *
     * @param bool $mail Whether to use PHP `mail()` (default is true). Set to `false` to use SMTP.
     *
     * @return self Return the Mailer class instance.
     */
    public function isMail(bool $mail = true): self 
    {
        if($mail){
            $this->client->isMail();
            return $this;
        }

        $this->client->isSmtp();
        return $this;
    }

    /**
     * Add a custom header to the email.
     * 
     * This method allows you to add custom headers to the email message.
     * 
     * @param string $key The header name.
     * @param string|null $value The header value (optional).
     *
     * @return self Return the Mailer class instance.
     */
    public function header(string $key, ?string $value = null): self 
    {
        $this->client->addHeader($key, $value);
        return $this;
    }

    /**
     * Add an email address to the recipient list.
     *
     * @param string $address The email address.
     * @param string $name The recipient's name (optional).
     *
     * @return self Return the Mailer class instance.
     */
    public function address(string $address, string $name = ''): self
    {
        $this->client->addAddress($address, $name);
        return $this;
    }

    /**
     * Add one or more email addresses to the recipient list.
     *
     * This method supports various input formats, including a comma-separated string, 
     * a numeric array of addresses, or an associative array of name-email pairs.
     *
     * @param array<string,string>|string[]|string $address A single email string, an array of emails, 
     *              or an array of name => email pairs.
     *
     * @return self Return the Mailer class instance.
     *
     * @example - Add multiple addresses:
     * 
     * ```php
     * // as comma-separated string
     * $mailer->addresses('example@gmail.com,example@yahoo.com');
     *
     * // as an indexed array
     * $mailer->addresses([
     *     'example@gmail.com',
     *     'example@yahoo.com'
     * ]);
     *
     * // as associated names
     * $mailer->addresses([
     *     'john@gmail.com' => 'John',
     *      'deo@yahoo.com' => 'Deo'
     * ]);
     * ```
     */
    public function addresses(array|string $address): self
    {
        $this->client->addAddresses($address);
        return $this;
    }

    /**
     * Set the notification address for read and delivery receipts.
     *
     * This method allows you to specify an email address where notifications should 
     * be sent regarding the status of the email, such as delivery or read receipts.
     *
     * @param string $address The email address to receive the notification.
     *
     * @return self Return the Mailer class instance.
     */
    public function notification(string $address): self
    {
        $this->client->setNotificationTo($address);
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
    public function reply(string $address, string $name = ''): self
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
     * @param MailTemplate|string $message The body content of the email.
     * 
     * @return self Return the Mailer class instance.
     */
    public function body(MailTemplate|string $message): self 
    {
        if($message instanceof MailTemplate){
            $this->subject($message->getSubject() ?? '')
                ->text($message->getText() ?? '');

            $this->client->Body = $message->getHtml() 
                ?? $message->getText() 
                ?? '';

            foreach($message->getFiles() ?? [] as $attach){
                $this->client->addAttachment(
                    $attach['path'], 
                    $attach['name'] ?? '', 
                    $attach['encoding'] ?? 'base64', 
                    $attach['type'] ?? '', 
                    $attach['disposition'] ?? 'attachment'
                );
            }

            return $this;
        }

        $this->client->Body = $message;
        return $this;
    }

    /**
     * Sets the alternative body of the email message.
     * 
     * This allow accept plain message, no HTML.
     *
     * @param string $message The alternative body content of the email.
     * 
     * @return self Return the Mailer class instance.
     */
    public function text(string $message): self 
    {
        $this->client->AltBody = trim(
            html_entity_decode(
                preg_replace('/\s+/', ' ', strip_tags($message)),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            )
        );

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
        $this->client->Subject = trim(
            html_entity_decode(
                strip_tags($subject), 
                ENT_QUOTES | ENT_HTML5, 
                'UTF-8'
            )
        );
        return $this;
    }

    /**
     * Add an attachment from a path on the filesystem.
     *
     * @param string $path Path to the attachment
     * @param string $name Overrides the attachment name
     * @param string $encoding File encoding (see $Encoding)
     * @param string $type MIME type, e.g. `image/jpeg`; determined automatically from $path if not specified
     * @param string $disposition The content disposition to use (default: `attachment`).
     *
     * @return self Return the Mailer class instance.
     * @throws MailerException Throws if file could not be read.
     */
    public function attachment(
        string $path, 
        string $name = '', 
        string $encoding = 'base64', 
        string $type = '', 
        string $disposition = 'attachment'
    ): self 
    {
        try{
            $this->client->addAttachment($path, $name, $encoding, $type, $disposition);
        }catch(Throwable $e){
            if($e instanceof AppException){
                throw $e;
            }

            throw new MailerException($e->getMessage(), $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * Send prepared email.
     * 
     * @param MailTemplate|string|null $message Optionally pass message body in send method.
     * 
     * @return bool Return true if the email was sent successfully, false otherwise.
     * @throws MailerException Throws if error occurred while sending email.
     */
    public function send(MailTemplate|string|null $message = null): bool
    {
        if($message){
            $this->body($message);
        }

        // Set from here, to allow overriding config
        $this->client->setFrom(
            $this->from, 
            $this->fromName, 
            $this->fromAuto
        );

        try{
            return $this->client->send();
        }catch(Throwable $e){

            $e = ($e instanceof AppException) ? $e : new MailerException($e->getMessage(), $e->getCode(), $e);

            if(!PRODUCTION){
                throw $e;
            }

            $e->log();
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
        $this->fromName = APP_NAME;
        $this->from = env('smtp.email.sender', 'hello@' . HOST_NAME);

        $this->client->SMTPDebug = self::isDebuggable() ? 3 : 0;
        $this->client->CharSet = self::getCharset(env('smtp.charset'));
        $this->client->XMailer = Luminova::copyright();

        if ((bool) env('smtp.use.credentials')) {
            $this->client->isSMTP();
            $this->client->Host = env('smtp.host');
            $this->client->Port = (int) env('smtp.port', -1);

            if ((bool) env('smtp.use.password')) {
                $this->client->SMTPAuth = true;
                $this->client->Username = env('smtp.username');
                $this->client->Password = env('smtp.password');
            }

            $this->client->SMTPSecure = self::getEncryption(env('smtp.encryption'));
        } else {
            $this->client->isMail();
        }

        $this->client->isHTML(true);
        $this->client->initialize();
    }

    /**
     * Determine whether debugging is enabled.
     *
     * @return bool Returns true if debugging is enabled, false otherwise.
     */
    private static function isDebuggable(): bool
    {
        return !PRODUCTION && (bool) env('smtp.debug');
    }

    /**
     * Get the encryption type.
     *
     * @param string $encryption The encryption type.
     *
     * @return string Returns the encryption type constant.
     */
    private static function getEncryption(string $encryption): string
    {
        return match($encryption){
            'tls' => 'tls',
            'ssl' => 'ssl', 
            default => 'tls'
        };
    }

    /**
     * Get the character encoding.
     *
     * @param string $charset The character encoding.
     *
     * @return string Returns the character encoding constant.
     */
    private static function getCharset(string $charset): string
    {
        return match($charset){
            'utf8'      => 'utf-8',
            'iso88591'  => 'iso-8859-1',
            'ascii'     => 'us-ascii',
            default     => 'utf-8'
        };
    }

    /**
     * Parse the input string to separate the name and email address.
     *
     * Supports:
     * - "Name <email@example.com>" → ['Name', 'email@example.com']
     * - "email@example.com" → ['', 'email@example.com']
     * - "<email@example.com>" → ['', 'email@example.com']
     *
     * @param string $input The input string containing name and/or email address.
     * 
     * @return array Return the parsed name and email address.
     * @internal
     */
    public static function getAddressInfo(string $input): array
    {
        $input = trim(preg_replace('/[\r\n]+/', '', $input));

        if ($input === '') {
            return ['', ''];
        }

        if (preg_match('/^(.+?)\s*<([^>]+)>$/', $input, $match)) {
            return [trim($match[1]), $match[2]];
        }

        if (preg_match('/^<([^>]+)>$/', $input, $match)) {
            return ['', $match[1]];
        }
        
        return ['', $input];
    }
}