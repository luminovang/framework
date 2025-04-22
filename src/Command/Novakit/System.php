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
use \Luminova\Seo\Sitemap;
use \Luminova\Http\Network;
use \Luminova\Command\Utils\Text;
use \Luminova\Security\Crypter;
use \SplFileObject;
use \Throwable;

class System extends BaseConsole 
{
    /**
     * {@inheritdoc}
     */
    protected string $group = 'System';

    /**
     * {@inheritdoc}
     */
    protected string $name = 'generator';

    /**
     * {@inheritdoc}
     */
    protected string|array $usages  = [
        'php novakit generate:key --help',
        'php novakit generate:sitemap --help',
        'php novakit env:add --help',
        'php novakit env:setup --help',
        'php novakit env:cache --help',
        'php novakit env:remove --help'
    ];

    /**
     * {@inheritdoc}
     */
    public function run(?array $options = []): int
    {
        $this->term->explain($options);
        $command = trim($this->term->getCommand());
        $noSave = (bool) $this->term->getOption('no-save', false);
        $key = $this->term->getAnyOption('key', 'k');
        $value = $this->term->getAnyOption('value', 'v');

        $runCommand = match($command){
            'generate:key' => $this->generateKey($noSave),
            'generate:sitemap' => $this->generateSitemap(),
            'env:add' => $this->addEnv($key, $value),
            'env:cache' => $this->cacheEnv(),
            'env:setup' => $this->setupEnv($this->term->getAnyOption('target', 't')),
            'env:remove' => $this->removeEnv($key),
            default => null
        };

        if ($runCommand === null) {
            return $this->term->oops($command);
        } 

        return (int) $runCommand;
    }

    /**
     * {@inheritdoc}
     */
    public function help(array $helps): int
    {
        return STATUS_ERROR;
    }

    /**
     * Add environment variable.
     * 
     * @param string $key Environment variable name. 
     * @param string $value Environment variable value.
     * 
     * @return int Status code.
     */
    private function addEnv(string $key, string $value = ''): int 
    {
        if($key === ''){
            $this->term->beeps();
            $this->term->error('Environment variable key cannot be an empty string');

            return STATUS_ERROR;
        }

        setenv($key, $value, true);
        $this->term->header();
        $this->term->success('Variable "' . $key . '" added successfully');
        $this->term->writeln('Optionally run "php novakit env:cache" to create updated cache version of environment veriables.');

        return STATUS_SUCCESS;
    }

    /**
     * Generate a cache version of environment variables.
     * 
     * @return int Status code.
     */
    private function cacheEnv(): int 
    {
        $path = root() .  '.env';
        $envCache = root('writeable/') . '.env-cache.php';

        if (!file_exists($path)) {
            $this->term->beeps();
            $this->term->error('Environment variable file not found at the application root.');

            return STATUS_ERROR;
        }

        $ignoreKeys = [];
        $ignore = $this->term->getAnyOption('ignore', 'i');

        if($ignore){
           $ignoreKeys = explode(',', $ignore);
        }

        try {
            $entry = [];
            $file = new SplFileObject($path, 'r');

            while (!$file->eof()) {
                $line = trim($file->fgets());

                if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
                    continue;
                }

                [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
                $key = trim($key);

                if(!$key || in_array($key, $ignoreKeys)){
                    continue;
                }

                if(setenv($key, $value)){
                    $entry[$key] = env($key);
                }
            }

            if($entry !== [] && __cache_env($entry, $envCache)){
                $this->term->header();
                $this->term->success('Environment variable cache was successfully created.');

                return STATUS_SUCCESS;
            }
        } catch (Throwable $e) {
            $this->term->beeps();
            $this->term->error('Failed to create cache for environment variables: ' . $e->getMessage());
            return STATUS_ERROR;
        }

        $this->term->beeps();
        $this->term->error('Failed to create cache for environment variables.');
        return STATUS_SUCCESS;
    }

    /**
     * Set up the environment based on the specified target.
     *
     * This function initializes the environment configuration for a specific target,
     * such as 'telegram'. It validates the target, executes the appropriate setup
     * method, and handles errors for unsupported targets.
     *
     * @param string|null $target The target environment to set up (e.g., 'telegram').
     *                            If null, an error will be triggered.
     *
     * @return int Returns STATUS_SUCCESS on successful setup, STATUS_ERROR otherwise.
     *             STATUS_ERROR is returned if the target is missing or unsupported.
     */
    private function setupEnv(?string $target): int 
    {
        if (!$target) {
            $this->term->beeps();
            $this->term->error('Missing target environment context. Usage: `-t=telegram`.');
            return STATUS_ERROR;
        }

        $contextStatus = match ($target) {
            'telegram' => $this->setupTelegram($target),
            'database' => $this->setupDatabaseConfig($target),
            default => null
        };

        if($contextStatus === null){
            $this->term->beeps();
            $this->term->error("Unsupported setup target: '{$target}'.");
            return STATUS_ERROR;
        }

        return $contextStatus;
    }

    /**
     * Sets up the header for environment configuration.
     *
     * This function creates a formatted text block to display as a header
     * when configuring environment variables for a specific target.
     *
     * @param string $target The name of the target environment being configured.
     *                       This will be displayed in the header text.
     *
     * @return void
     */
    private function setupHeader(string $target): void 
    {
        $block = Text::block(
            sprintf(
                '⚙️  Configuring %s Environment Variables',
                ucwords($target)
            ), 
            Text::CENTER, 
            1, 
            'brightYellow',
            'blue',
            Text::FONT_BOLD,
            Text::BORDER_THICKER | Text::BORDER_RADIUS,
            'white'
        );

        $this->term->writeln($block);
    }

    /**
     * Sets up the database configuration by prompting the user for various settings.
     *
     * This function guides the user through a series of prompts to configure
     * database settings for both production and development environments.
     * It covers settings such as hostname, port, charset, connection pooling,
     * max connections, connection retries, persistent connections, query pre-parsing,
     * caching driver, connection driver, and database engine-specific options.
     *
     * @param string $target The target environment being set up (e.g., 'database').
     *                       Used for display purposes in the setup header.
     *
     * @return int Returns STATUS_SUCCESS upon successful completion of the database configuration.
     */
    private function setupDatabaseConfig(string $target): int
    {
        $this->setupHeader($target);
        
        $hostname = $this->term->input('Enter database hostname (default: localhost): ', 'localhost');
        setenv('database.hostname', $hostname, true);

        $port = $this->term->input('Enter database port (default: 3306): ', '3306');
        setenv('database.port', $port, true);

        $charset = $this->term->input('Enter database charset (default: utf8mb4): ', 'utf8mb4');
        setenv('database.charset', $charset, true);

        $connectionPool = $this->term->prompt(
            'Enable connection pooling?', 
            ['yes', 'no'], 'required|in_array(yes,no)'
        ) === 'yes';
        setenv('database.connection.pool', $connectionPool ? 'true' : 'false', true);

        $maxConnections = $this->term->input('Enter max database connections (default: 3): ', '3');
        setenv('database.max.connections', $maxConnections, true);

        $connectionRetry = $this->term->input('Enter number of connection retries (default: 1): ', '1');
        setenv('database.connection.retry', $connectionRetry, true);

        $persistentConnection = $this->term->prompt(
            'Enable persistent connections?', 
            ['yes', 'no'], 'required|in_array(yes,no)'
        ) === 'yes';
        setenv('database.persistent.connection', $persistentConnection ? 'true' : 'false', true);

        $emulatePreparse = $this->term->prompt(
            'Enable query pre-parsing emulation?', 
            ['yes', 'no'], 'required|in_array(yes,no)'
        ) === 'yes';
        setenv('database.emulate.preparse', $emulatePreparse ? 'true' : 'false', true);

        $prodUsername = $this->term->input('Enter production database username: ');
        setenv('database.username', $prodUsername, true);

        $prodDatabase = $this->term->input('Enter production database name: ');
        setenv('database.name', $prodDatabase, true);

        $prodPassword = $this->term->input('Enter production database password: ');
        setenv('database.password', $prodPassword, true);

        $cachingDriver = array_key_first($this->term->chooser(
            'Choose the cache driver for database builder class:',
            ['filesystem' => 'Filesystem', 'memcached' => 'Memcached'],
            false,
            false
        ));

        setenv(($cachingDriver ? '' : '; ') . 'database.caching.driver', $cachingDriver, true);

        $connectionDriver = array_key_first($this->term->chooser(
            'Choose the database connection driver:',
            ['PDO' => 'PDO Driver', 'MYSQLI' => 'MySQLi Driver'],
            true,
            false
        ));
        setenv('database.connection', $connectionDriver, true);

        if ($connectionDriver === 'PDO') {
            $map = [
                'MySQL' => 'mysql',
                'SQLite' => 'sqlite',
                'Oracle' => 'oci',
                'DBLib' => 'dblib',
                'CUBRID' => 'cubrid',
                'SQL Server' => 'sqlsrv',
                'PostgreSQL' => 'pgsql'
            ];
            $pdoEngine = $this->term->tablist(
                ['MySQL', 'SQLite', 'Oracle', 'DBLib', 'CUBRID', 'SQL Server','PostgreSQL'],
                0,
                'Specify your PDO database engine:'
            );
            setenv('database.pdo.engine', $map[$pdoEngine] ?? 'mysql', true);
        }

        $mysqlSocket = $this->term->prompt(
            'Force MySQL/PGSQL connection via socket?', 
            ['yes', 'no'],'required|in_array(yes,no)'
        ) === 'yes';
        setenv('database.mysql.socket', $mysqlSocket ? 'true' : 'false', true);

        $isSqlite = ($connectionDriver === 'PDO' && $pdoEngine === 'sqlite');

        if ($mysqlSocket) {
            $socketPath = $this->term->input('Enter MySQL/PGSQL socket path (e.g, /var/mysql/mysql.sock): ');
            setenv(($socketPath ? '' : '; ') . 'database.mysql.socket.path', $socketPath, true);
        }

        if ($isSqlite) {
            $sqlitePath = $this->term->input('Enter SQLite database path for production: ');
            if ($sqlitePath) {
                setenv('database.sqlite.path', $sqlitePath, true);
            }
        }

        // Development Database Configuration
        $forLocal = $this->term->prompt(
            'Do you want to setup database for development?',
            ['yes', 'no'],
            'required|in_array(yes,no)'
        ) === 'yes';

        if ($forLocal) {
            $devUsername = $this->term->input('Enter development database username: ');
            setenv('database.development.username', $devUsername, true);

            $devDatabase = $this->term->input('Enter development database name: ');
            setenv('database.development.name', $devDatabase, true);

            $devPassword = $this->term->input('Enter development database password: ');
            setenv('database.development.password', $devPassword, true);

            if ($isSqlite) {
                $devSqlitePath = $this->term->input('Enter SQLite database path for development: ');
                if ($devSqlitePath) {
                    setenv('database.development.sqlite.path', $devSqlitePath, true);
                }
            }
        }

        $this->term->success('Database configuration completed successfully.');
        return STATUS_SUCCESS;
    }

    /**
     * Sets up Telegram bot configuration by obtaining and setting the bot token and chat ID.
     *
     * This function attempts to retrieve the Telegram bot token and chat ID from various sources:
     * - Command line options
     * - Environment variables
     * - User input
     * - Telegram API (for chat ID)
     *
     * If successful, it sets the bot token and chat ID as environment variables.
     *
     * @return int Returns STATUS_SUCCESS if the setup is successful, STATUS_ERROR otherwise.
     */
    private function setupTelegram(string $target): int 
    {
        $this->setupHeader($target);
        $token = $this->term->getOption('token', env('telegram.bot.token'));
        $chatId = $this->term->getOption('chatid');

        if (!$token) {
            $token = $this->term->input('Enter your Telegram bot token: ');
        }

        if (!$token) {
            $this->term->error('Telegram setup failed: No bot token provided.');
            return STATUS_ERROR;
        }

        if (!$chatId) {
            try {
                $response = (new Network())->get("https://api.telegram.org/bot{$token}/getUpdates");

                if ($response->getStatusCode() === 200) {
                    $data = json_decode($response->getBody()->getContents(), true);
                    $ok = $data['ok'] ?? false;

                    if ($ok && !empty($data['result'])) {
                        foreach ($data['result'] as $update) {
                            if (isset($update['message']['chat']['id'])) {
                                $chatId = $update['message']['chat']['id'];
                                break;
                            }
                        }
                    }
                }

                if (!$chatId) {
                    $this->term->writeln("Unable to retrieve the Telegram chat ID.");
                    $this->term->writeln("If your bot is new, try sending it a message (e.g., 'Hi!').");
                    $this->term->writeln("Ensure your bot token is valid.");
                }

            } catch (Throwable $fe) {
                $this->term->error("Telegram API request failed: {$fe->getMessage()}");
            }
        }

        if (!$chatId) {
            $manually = $this->term->prompt(
                'Would you like to enter the chat ID manually?', 
                ['yes', 'no'], 
                'required|in_array(yes,no)'
            );

            if ($manually === 'yes') {
                $chatId = $this->term->input('Enter your Telegram chat ID: ');
            }
        }

        if ($chatId) {
            setenv('telegram.bot.token', $token, true);
            setenv('telegram.bot.chat.id', $chatId, true);
            $this->term->success('Telegram environment variables have been successfully updated.');
            return STATUS_SUCCESS;
        }

        $this->term->error('Failed to configure Telegram environment variables.');
        return STATUS_ERROR;
    }


    /**
     * Remove environment variable.
     * 
     * @param string $key Environment variable name. 
     * 
     * @return int Status code.
     */
    private function removeEnv(string $key): int 
    {
        if($key === ''){
            $this->term->beeps();
            $this->term->error('Environment variable key cannot be an empty string');

            return STATUS_ERROR;
        }

        $envFile = root() . '.env';
        $envCache = root('writeable/') . '.env-cache.php';
        $envContents = get_content($envFile);
        
        if($envContents === false){
            $this->term->beeps();
            $this->term->error('Failed to read environment file');
            return STATUS_ERROR;
        }
        
        if (str_contains($envContents, "$key=") && str_contains($envContents, "$key =")) {
            $newContents = preg_replace("/\b$key\b.*\n?/", '', $envContents);
            if (write_content($envFile, $newContents) !== false) {
                $wasCached = file_exists($envCache);
                $entries = $wasCached ? include_once $envCache : [];

                unset($_ENV[$key], $_SERVER[$key], $entries[$key]);

                if($wasCached){
                    __cache_env($entries, $envCache);
                }

                $this->term->header();
                $this->term->success('Variable "' . $key . '" was deleted successfully');

                return STATUS_SUCCESS;
            }
        }

        $this->term->beeps();
        $this->term->error('Variable "' . $key . '" not found or may have been deleted');

        return STATUS_ERROR;
    }

    /**
     * Generates sitemap 
     * 
     * @return int Status code 
     */
    private function generateSitemap(): int 
    {
        if(Sitemap::generate(null, $this->term)){
            return STATUS_SUCCESS;
        }

        $this->term->beeps();
        $this->term->newLine();
        $this->term->error('Sitemap creation failed');
    
        return STATUS_ERROR;
    }

    /**
     * Generates encryption sitekey.
     * 
     * @param bool $noSave Save key to env or just print.
     * 
     * @return int Status code 
     */
    private function generateKey(bool $noSave): int 
    {
        $key = Crypter::generate_key(); 

        if($key === false){
            $this->term->beeps();
            $this->term->error('Failed to generate application encryption key');

            return STATUS_ERROR;
        }

        $this->term->success('Application key generated successfully.');

        if($noSave){
            $this->term->newLine();
            $this->term->print($key . PHP_EOL);
        }else{
            setenv('app.key', $key, true);
        }
    
        return STATUS_SUCCESS;
    }
}