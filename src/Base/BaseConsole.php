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

use \Luminova\Base\BaseCommand;

abstract class BaseConsole extends BaseCommand 
{
    /**
     * {@inheritdoc}
    */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Handle execution of command in command controller class.
     *
     * @param array<string, mixed> $params Command arguments and parameters
     * 
     * @return int status code STATUS_SUCCESS on success else STATUS_ERROR
    */
    abstract public function run(?array $params = []): int;
}
