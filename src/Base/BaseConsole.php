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
     * Handle running and execution of command in console controller class.
     *
     * @param array<string,mixed>|null $params Command arguments and parameters.
     * 
     * @return int Return status code STATUS_SUCCESS on success else STATUS_ERROR.
     */
    abstract public function run(?array $params = null): int;
}
