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
namespace Luminova\Command\Novakit;

use \Luminova\Base\BaseConsole;
use \Luminova\Command\Utils\Color;
use \Luminova\Application\Foundation;
use \Luminova\Functions\IP;

class Server extends BaseConsole 
{
    /**
     * {@inheritdoc}
     */
    protected string $group = 'Server';

    /**
     * {@inheritdoc}
     */
    protected string $name = 'server';

    /**
     * {@inheritdoc}
     */
    protected array|string $usages = [
        'php novakit server --help'
    ];

    /**
     * @var int $offset port offset
     */
    private int $offset = 0;

    /**
     * @var int $tries number of tries
     */
    private int $tries = 10;

    /**
     * {@inheritdoc}
     */
    public function run(?array $options = []): int
    {
        $this->term->explain($options);

        $host = $this->term->getAnyOption('host', 'h', 'localhost');
        $testing = $this->term->getAnyOption('testing', 't', false);

        if($testing){
            $host = IP::getLocalNetworkAddress();

            if($host === false){
                $this->term->error("Failed to retrieve local network address.\n
                Manually specify your network address with '--host' option.");
                return STATUS_ERROR;
            }
        }

        $php = escapeshellarg($this->term->getAnyOption('php', 'b', PHP_BINARY));
        $port = (int) $this->term->getAnyOption('port', 'p', 8080) + $this->offset;
        $root = escapeshellarg(FRONT_CONTROLLER);
        $access ='http://' . $host . ':' . $port;
        $access = Color::style($access, 'green');

        $this->term->header();
        $this->term->writeln('Server Software: NovaKit/' . Foundation::NOVAKIT_VERSION . ' (Luminova) PHP/' . PHP_VERSION . ' (Development Server)', 'yellow');
        $this->term->writeln('Local access: ' . $access);
        if($testing){
            $this->term->writeln('Network access: ' . $access);
        }
        
        $this->term->writeln('Document root: ' . Color::style($root, 'cyan'));
        $this->term->newLine();
        $this->term->writeln('Press Ctrl-C to stop the server.');
        $this->term->newLine();

        // Apache's mod_rewrite functionality with settings.
        $rewrite = escapeshellarg(__DIR__ . '/mod_rewrite.php');
        $status = STATUS_ERROR;
        passthru($php . ' -S ' . $host . ':' . $port . ' -t ' . $root . ' ' . $rewrite, $status);

        if ($status === STATUS_ERROR && $this->offset < $this->tries) {
            $this->offset++;
            $this->run($options);
        }

        return STATUS_SUCCESS;
    }

    /**
     * {@inheritdoc}
     */
    public function help(array $helps): int
    {
        return STATUS_ERROR;
    }
}