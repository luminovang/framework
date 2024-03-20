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

use \Luminova\Base\BaseCommand;
use \Luminova\Command\TextUtils;
use \Luminova\Command\Novakit\Commands;

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
        static::listCommands();

        return STATUS_SUCCESS;
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
                self::writeln('   ' . self::color(TextUtils::rightPad($command['name'], 25), 'green') . $command['description']);
            }
            self::newLine();
        }
    }
}