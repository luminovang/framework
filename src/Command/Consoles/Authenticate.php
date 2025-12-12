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

use \Luminova\Base\Console;
use \Luminova\Command\Terminal;
use \Luminova\Database\Builder;
use \Luminova\Security\Password;
use \Luminova\Security\Encryption\Key;
use function \Luminova\Funcs\{root, get_content};

class Authenticate extends Console 
{
    /**
     * {@inheritdoc}
     */
    protected string $group = 'auth';

    /**
     * {@inheritdoc}
     */
    protected string $name = 'Authentication';

    /**
     * {@inheritdoc}
     */
    protected array|string $usages = [
        'php novakit auth --help'
    ];

    /**
     * {@inheritdoc}
     */
    public function run(?array $options = []): int
    {
        $context = trim($this->input->getArgument(0) ?? '');
        $sessionId = Terminal::getSystemId('auth_');

        if($context === 'login'){
            $username = $this->input->getAnyOption('user', 'u', null);
            $user = $this->findUser($username);

            if (!$user) {
                Terminal::error("Invalid username not found.");
                return STATUS_ERROR;
            }
    
            return $this->authenticate($user);
        }

        if($context === 'logout'){
            $sessionId = Terminal::getSystemId('auth_');

            if (!$sessionId) {
                Terminal::error("Failed to get system id.");
                return STATUS_ERROR;
            }
    
            return $this->deAuthenticate($sessionId);
        }

        $name = trim($this->input->getName());
        return Terminal::oops("$name $context");
    }

    /**
     * {@inheritdoc}
     */
    public function help(array $helps): int
    {
        return STATUS_ERROR;
    }

    private function findUser(?string $username): ?object
    {
        if(!$username){
            return null;
        }

        $result = Builder::table('novakit_users')
            ->find(['auth', 'content', 'online'])
            ->where('user', '=', $username)
            ->get();

        if(!$result){
            return null;
        }
        
        return $result;
    }

    public function deAuthenticate(string $systemId): int 
    {
        $input = root('/writeable/.cli_users/', "{$systemId}.php");
        
        if(is_file($input)){
            if(unlink($input)){
                return STATUS_SUCCESS;
            }

            return STATUS_ERROR;
        }

        return STATUS_SUCCESS;
    }

    public function isOnline(string $systemId): int 
    {
        $input = root('/writeable/.cli_users/', "{$systemId}.php");
        return is_file($input) ? STATUS_SUCCESS : STATUS_ERROR;
    }

    public function authenticate(object $user): int
    {
        $value = null;
        $hideFeedback = $this->input->getAnyOption('silent-login', 's');
        $skipPass = ($user->auth === 'password' && $user->content === '');

        if ($user->auth === 'password') {
            $value = Terminal::password(
                $skipPass ? 'Press Enter to skip password' : 'Enter password',
                $skipPass
            );            
        } elseif ($user->auth === 'key') {
            $input = Terminal::input('Enter private key, key path: or enter to get key locally: ');
        
            if(!$input && !PRODUCTION){
                $input = root('/writeable/keys/', 'cli-auth-private.key');

                if (!is_file($input)) {
                    $input = '';
                }
            }

            $value = ($input && is_file($input)) ? get_content($input) : (string) $input;
        }

        if (!$value && !$skipPass) {
            Terminal::error('Authentication failed: key not found or invalid.');
            return STATUS_ERROR;
        }

        $isValid = false;

        if($user->auth === 'password'){
            $isValid = ($skipPass && $value === '') || Password::verify($value, $user->content);
        }elseif($user->auth === 'key'){
            $key = $user->content;

            if(is_file($key)){
                $path = root(dirname($key), basename($key));
                $key = is_file($path) ? get_content($path) : '';
            }

            $isValid = $key && Key::isMatch($value, $key);
        }

        if ($isValid) {
            Terminal::header();

            if(!$hideFeedback){
                Terminal::success("Authentication successful.");
            }
            return STATUS_SUCCESS;
        }

        Terminal::error(($user->auth === 'password')
            ? 'Authentication failed: invalid password.'
            : 'Authentication failed: invalid key match.'
        );

        return STATUS_ERROR;
    }
}