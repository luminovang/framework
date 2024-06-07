<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace App\Controllers\Config;

use \Luminova\Base\BaseCron;

/**
 * Class Cron
 * 
 * Cron job scheduler for managing scheduled tasks.
 */
final class Cron extends BaseCron
{
    /**
     * Schedule the task for execution.
     */
    protected function schedule(): void 
    {
        $this->service('\App\Controllers\IssueCommand::schedule')
            ->seconds(5)
            ->log(root('/writeable/log/') . 'cron.log');
    }
}
