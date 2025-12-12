<?php
/**
 * Luminova Framework For Managing Tasks
 * Extend this interface to allow terminal command access within TasQueue worker classes.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Interface;

use \Luminova\Command\Input;

interface TaskWorkerInterface
{
    /**
     * Attach a terminal instance for use within the queue worker.
     *
     * This makes command arguments and options available inside the queue class.
     *
     * @param Input $input The instance of command input arguments/options.
     *
     * @return static Returns the instance of the class.
     */
    public function setTerminal(Input $input): self;
}