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

use \Luminova\Luminova;
use \Luminova\Base\Console;
use \Luminova\Http\Network\IP;
use \Luminova\Command\Terminal;
use \Luminova\Command\Utils\Color;

class Server extends Console 
{
    /**
     * {@inheritdoc}
     */
    protected string $group = 'server';

    /**
     * {@inheritdoc}
     */
    protected string $name = 'Server';

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
        $host = $this->input->getAnyOption('host', 'a', 'localhost');
        $testing = $this->input->getAnyOption('testing', 't', false);

        if($testing){
            $host = IP::getLocalNetworkAddress();

            if($host === false){
                Terminal::error("Failed to retrieve local network address.\n
                Manually specify your network address with '--host' option.");
                return STATUS_ERROR;
            }
        }

        $php = escapeshellarg($this->input->getAnyOption('php', 'b', PHP_BINARY));
        $port = (int) $this->input->getAnyOption('port', 'p', 8080) + $this->offset;
        $root = escapeshellarg(DOCUMENT_ROOT);
        $access ='http://' . $host . ':' . $port;
        $access = Color::style($access, 'green');
   
        Terminal::header();
        Terminal::writeln('Server Software: NovaKit/' . Luminova::NOVAKIT_VERSION . ' (Luminova) PHP/' . PHP_VERSION . ' (Development Server)', 'yellow');
        Terminal::writeln('Local access: ' . $access);
        if($testing){
            Terminal::writeln('Network access: ' . $access);
        }
        
        Terminal::writeln('Document root: ' . Color::style($root, 'cyan'));
        Terminal::newLine();
        Terminal::writeln('Press Ctrl-C to stop the server.');
        Terminal::newLine();

        // Apache's mod_rewrite functionality with settings.
        $status = STATUS_ERROR;
        $cmd = sprintf(
            'env FRAMEWORK_VERSION=%s NOVAKIT_VERSION=%s %s -S %s -t %s %s',
            escapeshellarg(Luminova::VERSION),
            escapeshellarg(Luminova::NOVAKIT_VERSION),
            $php,
            escapeshellarg($host . ':' . $port),
            $root,
            escapeshellarg(__DIR__ . '/mod_rewrite.php')
        );

        passthru($cmd, $status);

        if ($status === STATUS_ERROR) {
            Terminal::clear();

            if ($this->offset < $this->tries) {
                $this->offset++;
                $assignedPort = $port + $this->offset;
            
                Terminal::writeln("Server failed to start at: {$host}:{$port}", 'red');
                Terminal::writeln("Retrying at: {$host}:{$assignedPort}");
                Terminal::newLine();
                $this->run($options);

                return STATUS_ERROR;
            }

            Terminal::error("Server failed to start at: {$host}:{$port}");
            return STATUS_ERROR;
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