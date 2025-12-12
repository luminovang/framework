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

use Luminova\Command\Input;

/**
 * Implement this interface to enable terminal command input access
 * within TasQueue worker classes.
 *
 * @property Input|null $input The CLI input instance containing command arguments and options.
 */
interface TaskWorkerInterface
{
    /**
     * Set the command input for the queue instance.
     *
     * Makes the command arguments and options available within the queue.
     *
     * @param Input $input The command input instance.
     *
     * @return self Returns the current instance.
     */
    public function setCommandInput(Input $input): self;
}