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
namespace Luminova\Sessions\Managers;

use Luminova\Base\Configuration;
use Luminova\Exceptions\JsonException;
use Luminova\Interface\SessionManagerInterface;
use Luminova\Exceptions\InvalidArgumentException;

abstract class AbstractSessionManager implements SessionManagerInterface 
{
    /**
     * @var string KEY_PREFIX
     */
    protected final const KEY_PREFIX = '__lmv';

    /**
     * Cookie config. 
     * 
     * @var Configuration $config
     */
    protected ?Configuration $config = null;

    /**
     * The session storage namespace.
     * 
     * @var string $namespace
     */
    protected string $namespace = 'default';

    /**
     * {@inheritdoc}
     */
    public function __construct(protected string $storage = 'global') {}

    /**
     * {@inheritdoc}
     */
    public function setConfig(Configuration $config): void
    {
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function setStorage(string $storage): self 
    {
        $this->storage = self::parseName($storage);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setNamespace(string $namespace): self 
    {
        $this->namespace = self::parseName($namespace, true);

        return $this;
    }

    /** 
     * {@inheritdoc}
     */
    public function setItem(string $name, mixed $value): self
    {
        return $this->setItems([$name => $value]);
    }

    /**
     * {@inheritdoc}
     */
    public function getStorage(): string 
    {
        return $this->storage;
    }

    /** 
     * {@inheritdoc}
     */
    public function getItem(string $name, mixed $default = null): mixed
    {
        return $this->getItems()[$name] ?? $default;
    }

     /** 
     * {@inheritdoc}
     */
    public function hasItem(string $name): bool
    {
        return array_key_exists($name, $this->getItems());
    }

    /** 
     * {@inheritdoc}
     */
    public function toObject(?string $storage = null): ?object
    {
        $result = $this->toArray($storage);
 
        if($result === null || $result === []){
            return null;
        }

        if(!is_array($result)){
            throw new JsonException(
                'Session data must be an array to convert it to an object.'
            );
        }

        return (object) $result;
    }

    /**
     * Parse storage and namespace name.
     *
     * @param string $name
     * @param boolean $forNamespace
     * 
     * @return string
     * @throws InvalidArgumentException if an invalid name
     */
    private static function parseName(string $name, bool $forNamespace = false): string
    {
        $name = trim($name);

        if (
            $name === '' ||
            !preg_match('/\A[A-Za-z_-][A-Za-z0-9_.-]{0,63}\z/', $name)
        ) {
            throw new InvalidArgumentException(sprintf(
                'Invalid %s "%s". Use only letters, numbers, dots, underscores, hyphens, and a maximum of 64 characters.',
                $forNamespace ? 'namespace' : 'storage name',
                $name
            ));
        }

        return $name;
    }
}