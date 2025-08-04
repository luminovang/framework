<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Email\Clients;

use \Luminova\Email\Mailer;
use \Luminova\Interface\MailerInterface;
use \PHPMailer\PHPMailer\PHPMailer as MailerClient;

class PHPMailer implements MailerInterface
{
    /**
     * Instance of PHPMailer class.
     * 
     * @var MailerClient|null $mailer
     */
    private ?MailerClient $mailer = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(bool $exceptions = false)
    {
        $this->mailer = new MailerClient($exceptions);
        $this->mailer->Hostname = APP_HOSTNAME;
    }

    /**
     * {@inheritdoc}
     */
    public function getMailer(): MailerClient
    {
        return $this->mailer;
    }

    /**
     * Retrieve the mail client object.
     * 
     * @param string $method The method to call.
     * @param array $arguments Optional arguments to pass to the method.
     * 
     * @return mixed Return the result of the method.
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->mailer->{$method}(...$arguments);
    }

    /**
     * Magic method to dynamically set a property in the `$mailer` instance.
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
        $this->mailer->{$name} = $value;
    }

    /**
     * Magic method to retrieve a dynamically set property from the `$mailer` instance.
     *
     * If the property is not set, it returns `null` instead of triggering an error.
     *
     * @param string $name The name of the property being accessed.
     * 
     * @return mixed|null The value of the property if it exists, otherwise `null`.
     */
    public function __get(string $name): mixed 
    {
        return $this->mailer->{$name} ?? null;
    }

    /**
     * Magic method to check if a dynamic property exists in the `$mailer` instance.
     *
     * This is useful for `isset()` checks to determine whether a property has been set.
     *
     * @param string $name The name of the property to check.
     * 
     * @return bool `true` if the property exists, otherwise `false`.
     */
    public function __isset(string $name): bool 
    {
        return isset($this->mailer->{$name});
    }

    /**
     * Magic method to unset a dynamically set property in the `$mailer` instance.
     *
     * This removes the property from the instance, making it unavailable for future access.
     *
     * @param string $name The name of the property to unset.
     * 
     * @return void
     */
    public function __unset(string $name): void 
    {
        unset($this->mailer->{$name});
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(): void {}

    /**
     * {@inheritdoc}
     */
    public function setFrom(string $address, string $name = '', bool $auto = true): bool
    {
        return $this->mailer->setFrom($address, $name, $auto);
    }

    /**
     * {@inheritdoc}
     */
    public function setNotificationTo(string $address): bool
    {
        $x = $this->addHeader('Return-Receipt-To', $address);
        $y = $this->addHeader('Disposition-Notification-To', $address);
    
        return $x || $y;
    }

    /**
     * {@inheritdoc}
     */
    public function addHeader(string $key, ?string $value = null): bool 
    {
        return $this->mailer->addCustomHeader($key, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function addAddresses(string|array $address): bool
    {
        $count = 0;
        $address = is_string($address) ? explode(',', $address) : $address;

        foreach ($address as $name => $email) {
            [$name, $address] = Mailer::getAddress(trim($email));
            $this->mailer->addAddress($address, $name);
            $count++;
        }

        return $count > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function addAddress(string $address, string $name = ''): bool
    {
        return $this->mailer->addAddress($address, $name);
    }

    /**
     * {@inheritdoc}
     */
    public function addReplyTo(string $address, string $name = ''): bool
    {
        return $this->mailer->addReplyTo($address, $name);
    }

    /**
     * {@inheritdoc}
     */
    public function addCC(string $address, string $name = ''): bool
    {
       return $this->mailer->addCC($address, $name);
    }

    /**
     * {@inheritdoc}
     */
    public function addBCC(string $address, string $name = ''): bool
    {
        return $this->mailer->addBCC($address, $name);
    }

    /**
     * {@inheritdoc}
     */
    public function addAttachment(
        string $path, 
        string $name = '', 
        string $encoding = 'base64', 
        string $type = '', 
        string $disposition = 'attachment'
    ): bool
    {
        return $this->mailer->addAttachment($path, $name, $encoding, $type, $disposition);
    }

    /**
     * {@inheritdoc}
     */
    public function isSMTP(): void
    {
        $this->mailer->isSMTP();
    }

    /**
     * {@inheritdoc}
     */
    public function isMail(): void
    {
        $this->mailer->isMail();
    }

    /**
     * {@inheritdoc}
     */
    public function isHTML(bool $isHtml = true): void
    {
        $this->mailer->isHTML($isHtml);
    }

    /**
     * {@inheritdoc}
     */
    public function send(): bool
    {
        return $this->mailer->send();
    }
}