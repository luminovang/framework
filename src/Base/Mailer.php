<?php
/**
 * Luminova Framework Mailable Base Controller class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Base;

use \Luminova\Interface\LazyObjectInterface;

abstract class Mailer implements LazyObjectInterface
{
    /**
     * Define the email subject.
     * 
     * Return the subject line as a string or null for no subject.
     * This will appear as the email's subject in the recipient's inbox.
     *
     * @return string|null Return the email subject line
     */
    abstract public function getSubject(): ?string;

    /**
     * Define the HTML content for the email.
     *
     * Return the HTML formatted content as a string.
     * This will be rendered as the primary email body for clients that support HTML.
     * Return null if this email should not have HTML content.
     *
     * @return string|null Return the HTML email body content
     */
    abstract public function getHtml(): ?string;

    /**
     * Define the plain text alternative content for the email.
     *
     * Return the plain text version of the email content as a string.
     * This will be shown in email clients that don't support HTML or as a fallback.
     * Return null if no plain text version should be sent.
     *
     * @return string|null Return the plain text email body content
     */
    abstract public function getText(): ?string;

    /**
     * Define any file attachments for the email.
     *
     * Return an array of attachment definitions or null for no attachments.
     * Each attachment should be defined as an array with the following optional keys:
     * 
     * - path: (required) Absolute filesystem path to the file
     * - name: (optional) Custom filename for the attachment
     * - encoding: (default: 'base64') File encoding method
     * - type: (optional) MIME type of the attachment
     * - disposition: (default: 'attachment') Either 'attachment' or 'inline'
     *
     * Example:
     * [
     *     [
     *         'path' => '/path/to/file.pdf',
     *         'name' => 'document.pdf',
     *         'type' => 'application/pdf'
     *     ]
     * ]
     *
     * @return array<array<string,mixed>>|null Return an array of attachment configurations.
     */
    abstract public function getFiles(): ?array;
}