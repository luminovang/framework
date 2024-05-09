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

/**
 * Base class for mailer template implementations.
 */
abstract class BaseMailer
{
    /**
     * Get the subject of the email.
     *
     * @return string|null The subject of the email.
     */
    abstract public function getSubject(): ?string;

    /**
     * Get the HTML body of the email.
     *
     * @return string|null The body of the email.
     */
    abstract public function getHtml(): ?string;

    /**
     * Get the Text alternative email body non-HTML text.
     *
     * @return string|null The body Text.
     */
    abstract public function getText(): ?string;

    /**
     * Get the attachments of the email.
     *
     * @return array<string,string>|null An array of attachments for the email.
     * 
     * Array keys 
     * - path: The file path.
     * - name: Optional file name.
     * - encoding: File encoding (default: base64).
     * - type: Optional file type.
     * - disposition: File disposition (default: attachment)
     */
    abstract public function getFiles(): ?array;
}