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
namespace Luminova\Interface;

use \Luminova\Exceptions\MailerException;

interface MailerInterface
{
    /**
     * Constructor.
     *
     * @param bool $exceptions Weather to throw exceptions if error (default: false).
     */
    public function __construct(bool $exceptions = false);

    /**
     * Initialize mail client configurations.
     */
    public function initialize(): void;

    /**
     * Retrieve the mailer client instance.
     * 
     * @return Luminova\Email\Clients\NovaMailer|\PHPMailer\PHPMailer\PHPMailer|\Swift_Mailer Return instance of mailer client in use.
     */
    public function getMailer(): mixed;

    /**
     * Send the email.
     *
     * @return bool Return true if the email was sent successfully, false otherwise.
     * @throws MailerException
     */
    public function send(): bool;

    /**
     * Set the email sender's address.
     *
     * @param string $address The email address.
     * @param string $name The sender's name (optional).
     * @param bool   $auto Whether to automatically add the sender's name (optional).
     *
     * @return bool Return true if the sender's address was set successfully, false otherwise.
     */
    public function setFrom(string $address, string $name = '', bool $auto = true): bool;

    /**
     * Set the notification address for read and delivery receipts.
     *
     * This method allows you to specify an email address where notifications should 
     * be sent regarding the status of the email, such as delivery or read receipts. 
     * It sets both `Return-Receipt-To` (for delivery receipts) and 
     * `Disposition-Notification-To` (for read receipts), depending on your email client and configuration.
     *
     * @param string $address The email address to receive the notification.
     *
     * @return bool Return true if address was set successfully, false otherwise.
     */
    public function setNotificationTo(string $address): bool;

    /**
     * Add custom header.
     * 
     * @param string $name Header name.
     * @param string|null $value Optional header value.
     * 
     * @return bool Return true if header was added.
     */
    public function addHeader(string $name, ?string $value = null): bool;

    /**
     * Add one or more email addresses to the CC list.
     *
     * Accepts a single email string, an array of email addresses,
     * or an associative array of name => email pairs.
     *
     * @param string|array<int|string,string> $address  A single email string, an array of emails, or an array of name => email pairs.
     * 
     * @return bool Returns true if at least one address was added.
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
     *     'John' => 'john@gmail.com',
     *     'Deo' => 'deo@yahoo.com'
     * ]);
     * ```
     */
    public function addAddresses(string|array $address): bool;

    /**
     * Add an email address to the recipient list.
     *
     * @param string $address The email address.
     * @param string $name The recipient's name (optional).
     *
     * @return bool Return true if the address was added successfully, false otherwise.
     */
    public function addAddress(string $address, string $name = ''): bool;

     /**
     * Add a reply-to address.
     *
     * @param string $address The email address.
     * @param string $name The recipient's name (optional).
     *
     * @return bool Return true if the reply-to address was added successfully, false otherwise.
     */
    public function addReplyTo(string $address, string $name = ''): bool;

    /**
     * Add an email address to the recipient list.
     *
     * @param string $address The email address.
     * @param string $name The recipient's name (optional).
     *
     * @return bool Return true if the address was added successfully, false otherwise.
     */
    public function addCC(string $address, string $name = ''): bool;

    /**
     * Add an email address to the recipient list.
     *
     * @param string $address The email address.
     * @param string $name The recipient's name (optional).
     *
     * @return bool Return true if the address was added successfully, false otherwise.
     */
    public function addBCC(string $address, string $name = ''): bool;

    /**
     * Add an attachment from a path on the filesystem.
     *
     * @param string $path        Path to the attachment
     * @param string $name        Overrides the attachment name
     * @param string $encoding    File encoding (see $Encoding)
     * @param string $type        MIME type, e.g. `image/jpeg`; determined automatically from $path if not specified
     * @param string $disposition Disposition to use
     *
     * @return bool Return true, otherwise false if the file could not be found or read.
     * @throws MailerException Throws if file could not be read 
     */
    public function addAttachment(
        string $path, 
        string $name = '', 
        string $encoding = 'base64', 
        string $type = '', 
        string $disposition = 'attachment'
    ): bool;

    /**
     * Send messages using SMTP.
     * 
     * @return void
     */
    public function isSMTP(): void;

    /**
     * Send messages using PHP's mail() function.
     * 
     * @return void
     */
    public function isMail(): void;

    /**
     * Sets message type to HTML or plain.
     *
     * @param bool $isHtml True for HTML mode
     * 
     * @return void
     */
    public function isHTML(bool $isHtml = true): void;
}