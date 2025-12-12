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
use \Luminova\Composer\Builder;

class Project extends Console 
{
    /**
     * {@inheritdoc}
     */
    protected string $group = 'project';

    /**
     * {@inheritdoc}
     */
    protected string $name = 'Project';

    /**
     * {@inheritdoc}
     */
    protected string|array $usages = [
        "php novakit project --help",
    ];

    /**
     * {@inheritdoc}
     */
    public function run(?array $options = []): int
    {
        Builder::options([
            'progress'  => $this->input->getAnyOption('progress', 'p', true),
            'quiet'     => $this->input->getAnyOption('quiet', 'q', false),
            'verbose'   => $this->input->getVerbose(default: 3)
        ]);

        return match(true){
            $this->input->hasOption('export', 'e')   => Builder::export('builds'),
            $this->input->hasOption('archive', 'a')  => Builder::archive('project.zip'),
            $this->input->hasOption('import', 'i')   => Builder::import(
                 $this->input->getAnyOption('dir', 'd', false)
            ),
            default => STATUS_ERROR
        };
    }

    /**
     * {@inheritdoc}
     */
    public function help(array $helps): int
    {
        return STATUS_ERROR;
    }
}