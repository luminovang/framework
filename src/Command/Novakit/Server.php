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

class Server extends BaseCommand 
{
    /**
     * @var int $offset port offset
    */
    private int $offset = 0;

    /**
     * @var int $tries number of tries
    */
    private int $tries = 10;

    /**
     * @var string $group command group
    */
    protected string $group = 'Server';

    /**
     * @var string $name command name
    */
    protected string $name = 'server';

    /**
     * Options
     *
     * @var array<string, string> $options
    */
    protected array $options = [
        '--php'  => 'The PHP Binary [default: "PHP_BINARY"]',
        '--host' => 'The HTTP Host [default: "localhost"]',
        '--port' => 'The HTTP Host Port [default: "8080"]',
    ];


    /**
     * @param array $params terminal options
     * 
     * @return int 
    */
    public function run(?array $params = []): int
    {
        $this->explain($params);
   
        $php = escapeshellarg($this->getOption('php', PHP_BINARY));
        $host = $this->getOption('host', 'localhost');
        $port = (int) $this->getOption('port', 8080) + $this->offset;
        $root = escapeshellarg(PUBLIC_PATH);

        $this->writeln('NovaKit/' . parent::$version . ' (Luminova) PHP/' . PHP_VERSION. ' (Development Server)');
        $this->newLine();
        $this->writeln('Listening on http://' . $host . ':' . $port, 'green');
        $this->writeln('Document root is ' . $root, 'green');
        $this->newLine();
        $this->writeln('Press Ctrl-C to stop.');
        $this->newLine();

        // Mimic Apache's mod_rewrite functionality with user settings.
        $version = parent::$version;
        $rewrite = escapeshellarg(__DIR__ . '/mod_rewrite.php');

        // Call PHP's built-in webserver, making sure to set our
        // base path to the public folder, and to use the mod_rewrite file
        // to ensure our environment is set and it simulates basic mod_rewrite.
        passthru($php . ' -S ' . $host . ':' . $port . ' -t ' . $root . ' ' . $rewrite, $status);

        if ($status && $this->offset < $this->tries) {
            $this->offset++;

            $this->run($params);
        }

        return STATUS_SUCCESS;
    }
}