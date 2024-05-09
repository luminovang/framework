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

class PHPMailer extends MailerClient implements MailerInterface
{
    /**
     * {@inheritdoc}
    */
    public function __construct(bool $exceptions = false)
    {
        parent::__construct($exceptions);
    }

    /**
     * {@inheritdoc}
    */
    public function initialize(): void
    {
    }
}
