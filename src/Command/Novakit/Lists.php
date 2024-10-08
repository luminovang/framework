<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
*/
namespace Luminova\Command\Novakit;

use \Luminova\Base\BaseConsole;
use \Luminova\Command\Utils\Text;
use \Luminova\Command\Novakit\Commands;

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
    protected array $usages = [
        'php novakit list --help'
    ];

    /**
     * {@inheritdoc}
    */
    public function run(?array $options = []): int
    {
        static::listCommands();

        return STATUS_SUCCESS;
    }

    /**
     * {@inheritdoc}
    */
    public function help(array $helps): int
    {
        return STATUS_ERROR;
    }

    public static function listCommands(): void 
    {
        $commands = Commands::getCommands();
        $groupedCommands = [];
        
        foreach ($commands as $line) {
            $groupedCommands[$line['group']][] = $line;
        }

        foreach ($groupedCommands as $group => $list) {
            self::writeln($group);
            foreach ($list as $command) {
                self::writeln('   ' . self::color(Text::padEnd($command['name'], 25), 'green') . $command['description']);
            }

            self::newLine();
        }
    }
}