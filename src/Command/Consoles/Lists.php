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

use \Luminova\Command\Terminal;
use \Luminova\Base\Console;
use \Luminova\Command\Utils\Text;
use \Luminova\Command\Utils\Color;
use \Luminova\Command\Consoles\Commands;

class Lists extends Console 
{
    /**
     * {@inheritdoc}
     */
    protected string $group = 'list';

    /**
     * {@inheritdoc}
     */
    protected string $name = 'Lists';

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
        $this->term->perse($options);

        return $this->listCommands();
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
     * @return int
     */
    public function listCommands(): int 
    {
        $from = $this->term->getAnyOption('command', 'c', null);
        $commands = Commands::getCommands();
        $grouping = [];

        $from = $from ? (strstr($from, ':', true) ?: $from): null;
       
        foreach ($commands as $command => $line) {
            if ($from && !str_starts_with($command, $from)) {
                continue;
            }

            $length = strlen($command);
            $last = $grouping[$line['name']]['last'] ?? 0;

            if($length > $last){
                $grouping[$line['name']]['largest'] = $length;
            }

            $grouping[$line['name']]['commands'][] =  $line;
        }

        if($grouping === []){
            $this->term->oops($from ?? 'zsh');
            
            if(($suggest = Commands::suggest($from ?? 'zsh')) !== ''){
                $this->term->fwrite($suggest, Terminal::STD_ERR);
            }
            
            return STATUS_ERROR;
        }

        foreach ($grouping as $name => $list) {
            $this->term->writeln(Text::style("Available {$name} Commands", Text::FONT_BOLD));
            $this->term->newLine();
            
            foreach ($list['commands'] as $options) {
                $label = Color::style($options['group'], 'lightYellow');
                $spacing = Text::padding('', ($list['largest'] + 6) - strlen($options['group']), Text::RIGHT);
                $value = $options['description'];

                $this->term->writeln("  {$label}{$spacing}{$value}");
            }
            
            if(!$from){
                $this->term->newLine(2);
            }
        }

        return STATUS_SUCCESS;
    }
}