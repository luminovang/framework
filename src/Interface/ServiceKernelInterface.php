<?php
/**
 * Luminova Framework Kernel Interface.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Interface;

use \Psr\Log\LoggerInterface;
use \Luminova\Base\SessionHandler;
use \Psr\Http\Client\ClientInterface;
use \Luminova\Interface\MailerInterface;
use \Luminova\Interface\RouterInterface;
use \Luminova\Base\Cache as CacheProvider;
use \Luminova\Foundation\Core\Application;

/**
 * Interface ServiceKernelInterface
 *
 * Defines the core contract for an application kernel.  
 * 
 * > **Note:**
 * > This should be extended once as `app/Kernel.php`
 * 
 * @example - Usages:
 * ```php
 * use Luminova\Luminova;
 * 
 * $app = Luminova::kernel()->getApplication();
 * 
 * $kernel = App\Kernel::create();
 * $app = $kernel->getApplication();
 * ```
 */
interface ServiceKernelInterface
{
    /**
     * Create a kernel instance.
     *
     * Called by the framework to construct the application kernel.
     * Implementations must return a fully initialized kernel object.
     *
     * When `$shared` is true, the kernel may be prepared for shared use.
     * When false, a fresh, non-shared kernel instance must be returned.
     *
     * @param bool $shared Whether the kernel is intended to be shared.
     *
     * @return static Returns shared or new kernel instance.
     *
     * @internal Used by Luminova to create kernel instances.
     */
    public static function create(bool $shared): static;

    /**
     * Determine if the kernel should share a single application object.
     * 
     * Default `true`.
     *
     * Returning `true` instructs the framework to reuse the same
     * application kernel instance across requests or modules.  
     * Returning `false` allows each kernel invocation to create
     * a separate instance.
     *
     * @return bool Returns `true` to share object, `false` otherwise.
     */
    public static function shouldShareObject(): bool;

    /**
     * Get the routing system instance.
     *
     * Override this method if your application provides a custom router.
     * Returning null instructs the framework to use the default router.
     *
     * @return RouterInterface|null Returns the routing system instance, or null for the default.
     */
    public function getRoutingSystem(): ?RouterInterface;

    /**
     * Get the application instance.
     *
     * Override this method when using a custom Application class.
     * Returning null allows the framework to instantiate its default application.
     *
     * @return Application|null Returns the application instance, or null for the default.
     */
    public function getApplication(): ?Application;

    /**
     * Get the preferred logger instance.
     *
     * The returned logger must implement PSR's LoggerInterface.
     * Returning null instructs the framework to fall back to its default logger.
     *
     * Example:
     * ```php
     * return new MyLogger($config);
     * ```
     *
     * @return LoggerInterface|null Returns the logger instance, or null for the default.
     */
    public function getLogger(): ?LoggerInterface;

    /**
     * Get the mail client to use for the application.
     *
     * The mailer must implement `Psr\Mail\MailerInterface`.  
     * You may return either:
     * - an instance of the mailer,
     * - a class name implementing the mailer,
     * - or null to use the default mailer.
     * 
     * - You can use built-in clients like:  
     *   - `\Luminova\Components\Email\Clients\PHPMailer`  
     *   - `\Luminova\Components\Email\Clients\SwiftMailer`  
     *
     * @example - PHPMailer:
     * ```php
     * return new PHPMailer($config);
     * ```
     * @example - SwiftMailer:
     * ```php
     * return new SwiftMailer($config);
     * ```
     * 
     * @example - Example:
     * ```php
     * return new MyMailer($config);
     * ```
     *
     * @return MailerInterface|class-string<MailerInterface>|null Returns a mailer instance, 
     *      a mailer fully qualified name, 
     *      or null for the default mailer.
     */
    public function getMailer(): MailerInterface|string|null;

    /**
     * Return the HTTP client used for outbound requests.
     *
     * If `null` is returned, the default Luminova `Novio` client is used.
     * Custom clients must implement the PSR `ClientInterface`.
     *
     * **Luminova clients:**
     * - `\Luminova\Http\Client\Novio`   (default)
     * - `\Luminova\Http\Client\Guzzle`
     * 
     * @param array $config Optional configuration options for the HTTP client.
     *
     * @return ClientInterface|string|null Returns a client instance, a client class name, or null to use the default.
     */
    public function getHttpClient(array $config = []): ClientInterface|string|null;

    /**
     * Get the cache provider for the application.
     *
     * The cache provider must extend `Luminova\Base\Cache`.  
     * You may return either:
     * - an instance of the cache provider,
     * - a class name implementing the cache provider,
     * - or null to use the default cache provider.
     *
     * @return CacheProvider|class-string<CacheProvider>|null Returns a cache provider instance, 
     *      a cache provider fully qualified name, 
     *      or null for the default cache provider.
     */
    public function getCacheProvider(): CacheProvider|string|null;

    /**
     * Get the session handler for the application.
     *
     * The session handler must extend `Luminova\Base\SessionHandler`.  
     * You may return either:
     * - an instance of the session handler,
     * - a class name implementing the session handler,
     * - or null to use the default session handler.
     *
     * @return SessionHandler|class-string<SessionHandler>|null Returns a session handler instance, 
     *      a session handler fully qualified name, 
     *      or null for the default session handler.
     */
    public function getSessionHandler(): SessionHandler|string|null;
}