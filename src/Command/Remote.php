<?php 
/**
 * Luminova Framework Remove SSH connection
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Command;

use function \ssh2_connect;
use function \ssh2_exec;
use function \ssh2_auth_password;
use function \ssh2_auth_pubkey_file;
use function \stream_set_blocking;
use function \stream_get_contents;
use function \fclose;
use function \root;
use \Luminova\Exceptions\RuntimeException;

class Remote
{
    /**
     * Holds the SSH connection resource.
     * 
     * @var resource|null $connection
     */
    protected mixed $connection = null;

    /**
     * Flag to track if the connection is established.
     * 
     * @var bool $isConnected
     */
    private bool $isConnected = false;

    /**
     * Singleton instance.
     * 
     * @var self|null $instance
     */
    private ?self $instance = null;
    
    /**
     * The type of the pseudo-terminal (PTY) to allocate for the SSH session.
     * 
     * @var string|null $pty
     */
    protected ?string $pty = null;

    /**
     * The environment variables to set for the SSH session.
     * 
     * @var array|null $methods
     */
    protected ?array $methods = null;

    /**
     * The environment variables to set for the SSH session.
     * 
     * @var array|null $env
     */
    protected ?array $env = null;

    /**
     * The width of the terminal in columns.
     * 
     * @var int $width
     */
    protected int $width = 80;

    /**
     * The height of the terminal in rows.
     * 
     * @var int $height
     */
    protected int $height = 25;

    /**
     * Constructor to initialize the SSH connection details.
     *
     * @param string $username SSH username (e.g, `root`, `admin`, `foo`).
     * @param string|null $password SSH password (optional if using SSH keys).
     * @param string|null $private_key_path Path to the private SSH key (optional if using password).
     * @param string|null $public_key_path Path to the public SSH key (optional if using password).
     * @param int $port The remote SSH connection port IP (defaults: `22`).
     * @param string|null $server The remote server hostname or IP (defaults: `APP_HOSTNAME`).
     * 
     * @throws RuntimeException Throws if SSH2 extension is not enabled.
     */
    public function __construct(
        protected string $username,
        protected ?string $password = null,
        protected ?string $private_key_path = null,
        protected ?string $public_key_path = null,
        protected int $port = 22,
        protected ?string $server = null
    )
    {
        if(!function_exists('ssh2_connect')){
            throw new RuntimeException(
                "The SSH2 extension is not installed or enabled. Please follow these instructions to install it:\n" .
                "- For Ubuntu/Debian: Run `sudo apt-get install libssh2-1-dev && sudo pecl install ssh2`\n" .
                "- For CentOS/RHEL: Run `sudo yum install libssh2-devel && sudo pecl install ssh2`\n" .
                "- For macOS: Run `brew install libssh2 && pecl install ssh2`\n" .
                "- Once installed, add `extension=ssh2.so` to your `php.ini` file and restart your web server.\n" .
                "If you need more help, refer to the official documentation: https://www.php.net/manual/en/book.ssh2.php"
            );
        }
    }

    /**
     * Get or create a singleton instance of the Remote class.
     *
     * This method ensures that only one instance of the Remote class is created and returned.
     * If an instance doesn't exist, it creates one with the provided parameters.
     *
     * @param string $username SSH username (e.g., 'root', 'admin', 'foo').
     * @param string|null $password SSH password (optional if using SSH keys).
     * @param string|null $private_key_path Path to the private SSH key (optional if using password).
     * @param string|null $public_key_path Path to the public SSH key (optional if using password).
     * @param int $port The remote SSH connection port (defaults to 22).
     * @param string|null $server The remote server hostname or IP (defaults to APP_HOSTNAME).
     *
     * @return self Returns the singleton instance of the Remote class.
     * @throws RuntimeException Throws if SSH2 extension is not enabled or connection authentication fails.
     */
    public static function getInstance(
        string $username,
        ?string $password = null,
        ?string $private_key_path = null,
        ?string $public_key_path = null,
        int $port = 22,
        ?string $server = null
    ): self 
    {
        if(!self::$instance instanceof Remote){
            self::$instance = new self(
                $username, $password, 
                $private_key_path, $public_key_path, 
                $port, $server
            );
        }

        return self::$instance;
    }

    /**
     * Checks if the SSH connection is active and authenticated.
     *
     * @return bool Return true if connected, false otherwise.
     */
    public function isConnected(): bool
    {
        return $this->connection && $this->isConnected;
    }

    public function setMethods(array $methods): self 
    {
        $this->methods = $methods;
        return $this;
    }

    /**
     * Sets the environment variables for the SSH session.
     *
     * @param array $env An associative array of environment variables to set.
     *                   Keys are variable names, and values are their corresponding values.
     *
     * @return self Returns the current instance for method chaining.
     */
    public function setEnvironment(array $env): self 
    {
        $this->env = $env;
        return $this;
    }
    /**
     * Sets the PTY (Pseudo Terminal) type for the SSH connection.
     *
     * @param string $pty The PTY type to set (e.g., 'xterm', 'vt100', etc.).
     * 
     * @return self Returns the current instance for method chaining.
     */
    public function setPty(string $pty): self 
    {
        $this->pty = $pty;
        return $this;
    }

    /**
     * Sets the width of the terminal for the SSH connection.
     *
     * @param int $width The width of the terminal window (in columns).
     * @return self Returns the current instance for method chaining.
     */
    public function setWidth(int $width): self 
    {
        $this->width = $width;
        return $this;
    }

    /**
     * Sets the height of the terminal for the SSH connection.
     *
     * @param int $height The height of the terminal window (in rows).
     * @return self Returns the current instance for method chaining.
     */
    public function setHeight(int $height): self 
    {
        $this->height = $height;
        return $this;
    }

    /**
     * Establishes an SSH connection using either password or public/private key pair authentication.
     *
     * @return bool Returns true if connection is successful, otherwise false.
     */
    public function connect(): bool 
    {
        if($this->isConnected()){
            return true;
        }

        $this->connection = ssh2_connect(
            $this->server ?? APP_HOSTNAME, 
            $this->port, 
            $this->methods
        );

        if (!$this->username) {
            return false;
        }

        if ($this->password) {
            $this->isConnected = ssh2_auth_password($this->connection, $this->username, $this->password);
        }
        
        if ($this->private_key_path && $this->public_key_path && !$this->isConnected) {
            $this->isConnected = ssh2_auth_pubkey_file(
                $this->connection, 
                $this->username, 
                $this->public_key_path, 
                $this->private_key_path
            );
        }

        return $this->isConnected;
    }

    /**
     * Executes a remote command on the server via SSH.
     * 
     * @param string $command The command to execute on the remote server.
     * @param string|null $executable The executable to run e.g., `php path/to/script.php`, (default: 'php /project/public/index.php').
     * 
     * @return string|false Returns the command output if successful, or false on failure.
     */
    public function execute(string $command, ?string $executable = null): string|false
    {
        if (!$this->isConnected()) {
            return false;
        }

        $executable ??= 'php ' . root('public') . 'index.php';
        $stream = ssh2_exec(
            $this->connection, 
            "{$executable} {$command}",
            $this->pty, $this->env,
            $this->width, $this->height
        );

        if (!is_resource($stream)) {
            return false;
        }

        stream_set_blocking($stream, true);
        $output = stream_get_contents($stream);
        fclose($stream);

        return $output;
    }

    /**
     * Closes the SSH connection. Optionally called to clean up.
     *
     * @return true Always returns true.
     */
    public function disconnect(): bool
    {
        if ($this->connection) {
            ssh2_disconnect($this->connection);
        }

        $this->isConnected = false;
        return true;
    }
}