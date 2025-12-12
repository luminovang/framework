<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace App;

use \App\Application;
use \Psr\Log\LoggerInterface;
use \Luminova\Base\SessionHandler;
use \Psr\Http\Client\ClientInterface;
use \Luminova\Interface\MailerInterface;
use \Luminova\Interface\RouterInterface;
use \Luminova\Base\Cache as CacheProvider;
use \Luminova\Interface\ServiceKernelInterface;

/**
 * {@inheritDoc}
 */
final class Kernel implements ServiceKernelInterface
{
    /**
     * {@inheritDoc}
     */
    public static function create(bool $shared): static
    {
        return new self();
    }

    /**
     * {@inheritDoc}
     */
    public static function shouldShareObject(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getRoutingSystem(): ?RouterInterface
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function getApplication(): ?Application
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function getLogger(): ?LoggerInterface
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function getMailer(): MailerInterface|string|null
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function getCacheProvider(): CacheProvider|string|null
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function getSessionHandler(): SessionHandler|string|null
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function getHttpClient(array $config = []): ClientInterface|string|null
    {
        return null;
    }
}