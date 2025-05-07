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

use \Luminova\Base\BaseConsole;
use \Luminova\Security\Crypter;
use \Luminova\Database\Builder;

class Authenticate extends BaseConsole 
{
    /**
     * {@inheritdoc}
     */
    protected string $group = 'Authentication';

    /**
     * {@inheritdoc}
     */
    protected string $name = 'auth';

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
        $this->term->perse($options);
        $context = trim($this->term->getArgument(1) ?? '');
        $sessionId = $this->term->getSystemId('auth_');

        if($context === 'login'){
            $username = $this->term->getAnyOption('user', 'u', null);
            $user = $this->findUser($username);

            if (!$user) {
                $this->term->error("Invalid username not found.");
                return STATUS_ERROR;
            }
    
            return $this->authenticate($user);
        }

        if($context === 'logout'){
            $sessionId = $this->term->getSystemId('auth_');

            if (!$sessionId) {
                $this->term->error("Failed to get system id.");
                return STATUS_ERROR;
            }
    
            return $this->deAuthenticate($sessionId);
        }

        $command = trim($this->term->getCommand());
        return $this->term->oops("$command $context");
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
            ->where('user', '=', $username)
            ->find(['auth', 'content', 'online']);

        if(!$result){
            return null;
        }
        
        return $result;
    }

    public function deAuthenticate(string $systemId): int 
    {
        $input = root('/writeable/.cli_users/') . "{$systemId}.php";
        
        if(file_exists($input)){
            if(unlink($input)){
                return STATUS_SUCCESS;
            }

            return STATUS_ERROR;
        }

        return STATUS_SUCCESS;
    }

    public function isOnline(string $systemId): int 
    {
        $input = root('/writeable/.cli_users/') . "{$systemId}.php";
        return file_exists($input) ? STATUS_SUCCESS : STATUS_ERROR;
    }

    public function authenticate(object $user): int
    {
        $value = null;
        $hideFeedback = $this->term->getAnyOption('silent-login', 's');
        $skipPass = ($user->auth === 'password' && $user->content === '');

        if ($user->auth === 'password') {
            $value = $this->term->password(
                $skipPass ? 'Press Enter to skip password' : 'Enter password',
                $skipPass
            );            
        } elseif ($user->auth === 'key') {
            $input = $this->term->input('Enter private key, key path: or enter to get key locally: ');
        
            if(!$input && !PRODUCTION){
                $input = root('/writeable/keys/') . 'cli-auth-private.key';

                if (!file_exists($input)) {
                    $input = '';
                }
            }

            $value = ($input && file_exists($input)) ? get_content($input) : (string) $input;
        }

        if (!$value && !$skipPass) {
            $this->term->error('Authentication failed: key not found or invalid.');
            return STATUS_ERROR;
        }

        $isValid = false;

        if($user->auth === 'password'){
            $isValid = ($skipPass && $value === '') || Crypter::isPassword($value, $user->content);
        }elseif($user->auth === 'key'){
            $key = $user->content;

            if(is_file($key)){
                $path = root(dirname($key)) . basename($key);
                $key = file_exists($path) ? get_content($path) : '';
            }

            $isValid = $key && Crypter::isKeyMatch($value, $key);
        }

        if ($isValid) {
            $this->term->header();

            if(!$hideFeedback){
                $this->term->success("Authentication successful.");
            }
            return STATUS_SUCCESS;
        }

        $this->term->error(($user->auth === 'password')
            ? 'Authentication failed: invalid password.'
            : 'Authentication failed: invalid key match.'
        );

        return STATUS_ERROR;
    }
}