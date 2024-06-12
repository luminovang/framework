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

class Server extends BaseConsole 
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
    protected array $options = [
        '--php'  => 'The PHP Binary [default: "PHP_BINARY"]',
        '--host' => 'The HTTP Host [default: "localhost"]',
        '--port' => 'The HTTP Host Port [default: "8080"]',
    ];

    /**
     * {@inheritdoc}
    */
    protected array $usages = [
        'php novakit server',
        'php novakit server --host localhost --port 8080',
    ];

    /**
     * {@inheritdoc}
    */
    public function run(?array $params = []): int
    {
        $this->explain($params);
   
        $php = escapeshellarg($this->getOption('php', PHP_BINARY));
        $host = $this->getOption('host', 'localhost');
        $port = (int) $this->getOption('port', 8080) + $this->offset;
        $root = escapeshellarg(FRONT_CONTROLLER);

        $this->header();
        $this->writeln('Server Software Information: NovaKit/' . Foundation::NOVAKIT_VERSION . ' (Luminova) PHP/' . PHP_VERSION. ' (Development Server)', 'yellow');
        $this->newLine();
        $this->writeln('Listening on http://' . $host . ':' . $port, 'green');
        $this->writeln('Document root is ' . $root, 'green');
        $this->newLine();
        $this->writeln('Press Ctrl-C to stop.');
        $this->newLine();

        // Apache's mod_rewrite functionality with settings.
        $rewrite = escapeshellarg(__DIR__ . '/mod_rewrite.php');
        passthru($php . ' -S ' . $host . ':' . $port . ' -t ' . $root . ' ' . $rewrite, $status);

        if ($status && $this->offset < $this->tries) {
            $this->offset++;

            $this->run($params);
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