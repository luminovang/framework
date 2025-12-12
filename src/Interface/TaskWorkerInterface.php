<?php
/**
 * Luminova Framework For Managing Tasks
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Interface;

use \Luminova\Command\Input;

/**
 * Extend this interface to allow terminal command access within TasQueue worker classes.
 * 
 * @property Input|null $input Provides access to CLI arguments and options inside the worker.
 */
interface TaskWorkerInterface
{
    /**
     * Attach a terminal input instance to the worker.
     *
     * Makes CLI arguments and options available during task execution.
     *
     * @param Input $input The instance of command input arguments/options.
     *
     * @return static Returns the instance of the class.
     */
    public function setTerminal(Input $input): self;
}