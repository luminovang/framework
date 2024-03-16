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

use \Luminova\Command\Terminal;
use \Luminova\Base\BaseCommand;
use \Luminova\Command\TextUtils;
use \Luminova\Command\Novakit\AvailableCommands;

class Lists extends BaseCommand 
{
    /**
     * @var string $group command group
    */
    protected string $group = 'Lists';

    /**
     * @var string $name command name
    */
    protected string $name = 'list';

    /**
     * Options
     *
     * @var array<string, string>
    */
    protected array $options = [];

    protected string|array $usages  = 'php novakit list';

    /**
     * @param array $params terminal options
     * 
     * @return int 
    */
    public function run(?array $params = []): int
    {
        self::listCommands();

        return STATUS_SUCCESS;
    }

    public static function listCommands(): void 
    {
        $commands = AvailableCommands::getCommands();
        $groupedCommands = [];
        foreach ($commands as $line) {
            $groupedCommands[$line['group']][] = $line;
        }

        foreach ($groupedCommands as $group => $list) {
            Terminal::writeln($group);
            foreach ($list as $command) {
                Terminal::writeln('   ' . Terminal::color(TextUtils::rightPad($command['name'], 25), 'green') . $command['description']);
            }
            Terminal::newLine();
        }
    }
}