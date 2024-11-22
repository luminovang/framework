<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Base;

use \Luminova\Interface\LazyInterface;
/**
 * Base class for mailer template implementations.
 */
abstract class BaseMailer implements LazyInterface
{
    /**
     * Get the subject of the email.
     *
     * @return string|null Return the subject of the email.
     */
    abstract public function getSubject(): ?string;

    /**
     * Get the HTML body of the email.
     *
     * @return string|null Return the HTML body of the email.
     */
    abstract public function getHtml(): ?string;

    /**
     * Get the Text alternative email body non-HTML text.
     *
     * @return string|null Return the plain text body of the email.
     */
    abstract public function getText(): ?string;

    /**
     * Get the attachments of the email.
     *
     * @return array<string,string>|null An array of attachments for the email.
     * 
     * Array keys:
     * 
     * - path: The file path.
     * - name: Optional file name.
     * - encoding: File encoding (default: base64).
     * - type: Optional file type.
     * - disposition: File disposition (default: attachment)
     */
    abstract public function getFiles(): ?array;
}