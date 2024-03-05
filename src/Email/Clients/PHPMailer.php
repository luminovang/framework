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

use \Luminova\Email\Clients\MailClientInterface;
use \PHPMailer\PHPMailer\PHPMailer as PHPMailerClient;

class PHPMailer extends PHPMailerClient implements MailClientInterface
{
    /**
     * Constructor.
     *
     * @param bool $exceptions Should we throw external exceptions?
     */
    public function __construct(bool $exceptions = false)
    {
        parent::__construct($exceptions);
    }

    public function initialize(): void
    {
    }
}
