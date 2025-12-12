<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Command\Consoles;

use \Luminova\Base\Console;
use \Luminova\Composer\Builder as AppBuilder;

class Builder extends Console 
{
    /**
     * {@inheritdoc}
     */
    protected string $group = 'builder';

    /**
     * {@inheritdoc}
     */
    protected string $name = 'Builder';

    /**
     * {@inheritdoc}
     */
    protected string|array $usages = [
        "php novakit build:project --help",
        "php novakit build:project --type=export",
        "php novakit build:project --type=archive"
    ];

    /**
     * {@inheritdoc}
     */
    public function run(?array $options = []): int
    {
        AppBuilder::options([
            'progress'  => $this->input->getAnyOption('progress', 'p', true),
            'quiet'     => $this->input->getAnyOption('quiet', 'q', false),
            'verbose'   => $this->input->getVerbose(default: 3)
        ]);

        $result = match($this->input->getAnyOption('type', 't')){
            'build', 'export', 'e' => AppBuilder::export('builds'),
            'zip', 'archive', 'a'  => AppBuilder::archive('project.zip'),
            default => null
        };

        return ($result === null) ? STATUS_ERROR : STATUS_SUCCESS;
    }

    /**
     * {@inheritdoc}
     */
    public function help(array $helps): int
    {
        return STATUS_ERROR;
    }
}