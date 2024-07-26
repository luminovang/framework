<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Command\Auth;

use \Luminova\Command\Auth\Handler;
use \Luminova\Exceptions\RuntimeException;

class Session
{
    private ?Handler $handler = null;
    private ?string $token = null;
    private array $data = [];
    private string $idFile = '';

    public function __construct(string $path = '/writeable/tmp/')
    {
        $this->handler = new Handler($path);
        $this->idFile = $this->handler->getSavePath('profile.txt');
        $this->token = $this->getToken();
    }

    private function getToken(): ?string
    {
        if (file_exists($this->idFile)) {
            $profiles = get_content($this->idFile);
            if($profiles !== false){
                $profiles = json_decode($profiles, true);
                $profiles = array_values($profiles);
                return $profiles[0]['token'] ?? null;
            }

            return null;
        } 

        $token = sha1(uniqid('', true));
        $data = [];
        $data[$token] = [
            'token' => $token,
            'id' => null,
            'hash' => null,
        ];

        write_content($this->idFile, json_encode($data));

        return $token;
    }

    public function start(): void
    {
        if($this->token === null){
            throw new RuntimeException('Invalid session token.');
        }

        $data = $this->handler->read($this->token);

        if ($data) {
            $this->data = unserialize($data);
        }
    }

    public function online()
    {
        return isset($this->data['__cli__']);
    }

    public function login(string $userId): self
    {
        $this->set('user_id', $userId);
        $this->set('__cli__', $this->token);
        $this->setProfile($userId);
        return $this;
    }

    private function setProfile(string $userId): bool
    {
        if (file_exists($this->idFile)) {
            $profiles = get_content($this->idFile);
            if($profiles !== false){
                $profiles = json_decode($profiles, true);
                $profiles[$this->token]['id'] = $userId;
                $profiles[$this->token]['hash'] = $userId . '+' . $this->token;
                return write_content($this->idFile, json_encode($profiles));
            }
        } 

        return false;
    }

    public function get(string $key)
    {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, mixed $value)
    {
        $this->data[$key] = $value;
        $this->save();
    }
    
    public function save()
    {
        if($this->token === null){
            throw new RuntimeException('Invalid session token.');
        }

        $this->handler->write($this->token, serialize($this->data));
    }

    public function destroy()
    {
        if($this->token === null){
            throw new RuntimeException('Invalid session token.');
        }

        $this->handler->destroy($this->token);
        if (file_exists($this->idFile)) {
            unlink($this->idFile);
        }
    }
}