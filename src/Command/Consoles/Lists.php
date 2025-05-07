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

use \Luminova\Base\BaseConsole;
use \Luminova\Command\Utils\Text;
use \Luminova\Command\Utils\Color;
use \Luminova\Command\Consoles\Commands;

class Lists extends BaseConsole 
{
    /**
     * {@inheritdoc}
     */
    protected string $group = 'Lists';

    /**
     * {@inheritdoc}
     */
    protected string $name = 'list';

    /**
     * {@inheritdoc}
     */
    protected array|string $usages = [
        'php novakit list --help'
    ];

    /**
     * {@inheritdoc}
     */
    public function run(?array $options = []): int
    {
        $this->listCommands();

        return STATUS_SUCCESS;
    }

    /**
     * {@inheritdoc}
     */
    public function help(array $helps): int
    {
        return STATUS_ERROR;
    }

    /**
     * List all available luminova (novakit) commands.
     * 
     * @return void
     */
    public function listCommands(): void 
    {
        $commands = Commands::getCommands();
        $groupedCommands = [];
        
        foreach ($commands as $line) {
            $groupedCommands[$line['group']][] = $line;
        }

        foreach ($groupedCommands as $group => $list) {
            $this->term->writeln($group);
            
            foreach ($list as $command) {
                $name = Color::style(Text::padding($command['name'], 25, Text::LEFT), 'green');
                $this->term->writeln('   ' . $name . $command['description']);
            }

            $this->term->newLine();
        }
    }
}