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
    protected array $usages = [
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
        $this->explain($options);

        $host = $this->getAnyOption('host', 'h', 'localhost');
        $testing = $this->getAnyOption('testing', 't', false);

        if($testing){
            $host = IP::getLocalNetworkAddress();

            if($host === false){
                $this->error('Failed to retrieve local network address.');
            }
        }

        $php = escapeshellarg($this->getAnyOption('php', 'b', PHP_BINARY));
        $port = (int) $this->getAnyOption('port', 'p', 8080) + $this->offset;
        $root = escapeshellarg(FRONT_CONTROLLER);
        $access = $this->color('http://' . $host . ':' . $port, 'green');

        $this->header();
        $this->writeln('Server Software: NovaKit/' . Foundation::NOVAKIT_VERSION . ' (Luminova) PHP/' . PHP_VERSION . ' (Development Server)', 'yellow');
        $this->writeln('Local access: ' . $access);
        if($testing){
            $this->writeln('Network access: ' . $access);
        }
        $this->writeln('Document root: ' . $this->color( $root, 'cyan'));
        $this->newLine();
        $this->writeln('Press Ctrl-C to stop the server.');
        $this->newLine();

        // Apache's mod_rewrite functionality with settings.
        $rewrite = escapeshellarg(__DIR__ . '/mod_rewrite.php');
        $status = STATUS_ERROR;
        passthru($php . ' -S ' . $host . ':' . $port . ' -t ' . $root . ' ' . $rewrite, $status);

        if ($status && $this->offset < $this->tries) {
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