<?php 
/**
 * Luminova Framework Http development server.
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
use \Luminova\Command\Utils\Text;
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
     * {@inheritdoc}
     */
    protected array $aliases = [
        'serve', 'http'
    ];

    /**
     * {@inheritdoc}
     */
    public function help(array $helps): int
    {
        return STATUS_ERROR;
    }

    /**
     * {@inheritdoc}
     */
    public function run(?array $options = []): int
    {
        $host = $this->input->getAnyOption('host', 'a', null);
        $testing = $this->input->hasOption('testing', 't');
        $port = (int) $this->input->getAnyOption('port', 'p', 8080);

        $port = filter_var($port, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 1,
                'max_range' => 65535,
            ]
        ]);

        if ($port === false) {
            Terminal::error("Invalid port. Must be between 1 and 65535.");
            return STATUS_ERROR;
        }

        $host = ($host === true) ? null : $host;

        if($testing && !$host){
            $host = IP::getLocalNetworkAddress();

            if($host === false){
                Terminal::error(
                    "Failed to resolve local network address.\n" .
                    "Use '--host' to set it manually."
                );
                return STATUS_ERROR;
            }
        }

        $php = $this->input->getAnyOption('php', 'b', PHP_BINARY);
        $retry = (int) $this->input->getAnyOption('retry', 'r', 5);
        $host ??= '127.0.0.1';

        return $this->serve(
            $php,
            $host,
            $port,
            $retry,
            $testing,
            $this->input->hasOption('json', 'j')
        );
    }

    /**
     * Start the Luminova development server.
     * 
     * @param string $php Path to the PHP binary used to run the server.
     * @param string $host Hostname or IP address to bind the server to.
     * @param int $basePort Initial port number to start the server on.
     * @param int $maxTries Maximum number of retry attempts for finding an available port.
     * @param bool $testing Whether to enable network access mode for external devices.
     * @param bool $json Whether to output machine-readable JSON instead of CLI output.
     *
     * @return int Returns STATUS_SUCCESS on successful server start,
     *             or STATUS_ERROR if all attempts fail.
     */
    private function serve(
        string $php, 
        string $host, 
        int $basePort, 
        int $maxTries, 
        bool $testing = false,
        bool $json = false
    ): int
    {
        $modRewrite = escapeshellarg(__DIR__ . '/mod_rewrite.php');
        $fmVersion = escapeshellarg(Luminova::VERSION);
        $novakitVersion = escapeshellarg(Luminova::NOVAKIT_VERSION);
        $root = escapeshellarg(DOCUMENT_ROOT);

        $public = Color::style(DOCUMENT_ROOT, 'cyan');
        $software = Color::style(
            'NovaKit/' . Luminova::NOVAKIT_VERSION .
            ' (Luminova) PHP/' . PHP_VERSION . ' (Development Server)',
            'yellow'
        );

        for ($offset = 0; $offset <= $maxTries; $offset++) {
            $port = $basePort + $offset;
            $address = "{$host}:{$port}";

            $cmd = sprintf(
                'env LUMINOVA_VERSION=%s NOVAKIT_VERSION=%s %s -S %s -t %s %s',
                $fmVersion,
                $novakitVersion,
                $php,
                escapeshellarg($address),
                $root,
                $modRewrite
            );

            Terminal::clear();

            if ($json) {
                Terminal::writeln(json_encode([
                    'host' => $host,
                    'port' => $port,
                    'url'  => "http://{$address}",
                ], JSON_UNESCAPED_SLASHES));
            } else {
                $url = Color::style("http://{$address}", 'green');

                $messages = ['Local access' => $url];

                if ($testing) {
                    $messages['Network access'] = $url;
                }

                $messages['Document root'] = $public;
                $messages['Server software'] = $software;

                Terminal::header();
                Terminal::newLine();
                
                foreach($messages as $key => $message){
                    $spacing = Text::padding('', 20 - strlen($key), Text::RIGHT);

                    Terminal::writeln("{$key}:{$spacing}{$message}");
                }

                Terminal::newLine();
                Terminal::writeln('Press Ctrl+C to stop the server.');
                Terminal::newLine();
            }

            $status = STATUS_ERROR;

            passthru($cmd, $status);

            if ($status === STATUS_SUCCESS) {
                return STATUS_SUCCESS;
            }

            if ($offset < $maxTries) {
                $newPort = Color::style((string) ($port + 1), 'green');
                Terminal::clear();
                Terminal::header();
                Terminal::newLine();
                
                Terminal::writeln("Failed to start at: {$host}:{$port}", 'red');
                Terminal::writeln("Retrying on port: {$newPort}");
                Terminal::spinner(spins: 5);
            }
        }

        Terminal::clear();
        Terminal::error("Failed to start server after {$maxTries} attempts.");
        return STATUS_ERROR;
    }
}