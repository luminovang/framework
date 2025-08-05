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

use \Luminova\Command\Terminal;
use \Luminova\Interface\LazyObjectInterface;

interface TaskWorkerInterface
{
    /**
     * Attach a terminal instance for use within the queue worker.
     *
     * This makes command arguments and options available inside the queue class.
     *
     * @param Terminal|LazyObjectInterface $term Terminal instance that provides command arguments/options.
     *
     * @return static Returns the instance of the class.
     */
    public function setTerminal(LazyObjectInterface|Terminal $term): self;
}