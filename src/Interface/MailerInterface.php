<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Interface;

interface MailerInterface
{
    /**
     * Constructor.
     *
     * @param bool $exceptions Should we throw external exceptions?
    */
    public function __construct(bool $exceptions = false);

    /**
     * 
    */
    public function initialize(): void;

    /**
     * Send the email.
     *
     * @return bool True if the email was sent successfully, false otherwise.
     * @throws MailerException
    */
    public function send(): bool;

    /**
     * Set the email sender's address.
     *
     * @param string $address The email address.
     * @param string $name    The sender's name (optional).
     * @param bool   $auto    Whether to automatically add the sender's name (optional).
     *
     * @return bool True if the sender's address was set successfully, false otherwise.
     */
    public function setFrom(string $address, string $name = '', bool $auto = true): bool;

    /**
     * Add an email address to the recipient list.
     *
     * @param string $address The email address.
     * @param string $name    The recipient's name (optional).
     *
     * @return bool True if the address was added successfully, false otherwise.
     */
    public function addAddress(string $address, string $name = ''): bool;

     /**
     * Add a reply-to address.
     *
     * @param string $address The email address.
     * @param string $name    The recipient's name (optional).
     *
     * @return bool True if the reply-to address was added successfully, false otherwise.
     */
    public function addReplyTo(string $address, string $name = ''): bool;

    /**
     * Add an email address to the recipient list.
     *
     * @param string $address The email address.
     * @param string $name    The recipient's name (optional).
     *
     * @return bool True if the address was added successfully, false otherwise.
     */
    public function addCC(string $address, string $name = ''): bool;

    /**
     * Add an email address to the recipient list.
     *
     * @param string $address The email address.
     * @param string $name    The recipient's name (optional).
     *
     * @return bool True if the address was added successfully, false otherwise.
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