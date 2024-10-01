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