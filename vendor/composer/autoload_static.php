<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit9bbe5f33f53702290b6f40e5fff6e1ff
{
    public static $prefixLengthsPsr4 = array (
        'L' => 
        array (
            'Luminova\\' => 9,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Luminova\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'Luminova\\Ai\\Model' => __DIR__ . '/../..' . '/src/Ai/Model.php',
        'Luminova\\Ai\\Models\\OpenAI' => __DIR__ . '/../..' . '/src/Ai/Models/OpenAI.php',
        'Luminova\\Application\\Caller' => __DIR__ . '/../..' . '/src/Application/Caller.php',
        'Luminova\\Application\\Factory' => __DIR__ . '/../..' . '/src/Application/Factory.php',
        'Luminova\\Application\\Foundation' => __DIR__ . '/../..' . '/src/Application/Foundation.php',
        'Luminova\\Application\\Services' => __DIR__ . '/../..' . '/src/Application/Services.php',
        'Luminova\\Arrays\\ArrayUtil' => __DIR__ . '/../..' . '/src/Arrays/ArrayUtil.php',
        'Luminova\\Arrays\\Lists' => __DIR__ . '/../..' . '/src/Arrays/Lists.php',
        'Luminova\\Attributes\\AttrCompiler' => __DIR__ . '/../..' . '/src/Attributes/AttrCompiler.php',
        'Luminova\\Attributes\\Error' => __DIR__ . '/../..' . '/src/Attributes/Error.php',
        'Luminova\\Attributes\\Prefix' => __DIR__ . '/../..' . '/src/Attributes/Prefix.php',
        'Luminova\\Attributes\\Route' => __DIR__ . '/../..' . '/src/Attributes/Route.php',
        'Luminova\\Base\\BaseCache' => __DIR__ . '/../..' . '/src/Base/BaseCache.php',
        'Luminova\\Base\\BaseCallable' => __DIR__ . '/../..' . '/src/Base/BaseCallable.php',
        'Luminova\\Base\\BaseCommand' => __DIR__ . '/../..' . '/src/Base/BaseCommand.php',
        'Luminova\\Base\\BaseConfig' => __DIR__ . '/../..' . '/src/Base/BaseConfig.php',
        'Luminova\\Base\\BaseConsole' => __DIR__ . '/../..' . '/src/Base/BaseConsole.php',
        'Luminova\\Base\\BaseController' => __DIR__ . '/../..' . '/src/Base/BaseController.php',
        'Luminova\\Base\\BaseMailer' => __DIR__ . '/../..' . '/src/Base/BaseMailer.php',
        'Luminova\\Base\\BaseModel' => __DIR__ . '/../..' . '/src/Base/BaseModel.php',
        'Luminova\\Base\\BaseSessionHandler' => __DIR__ . '/../..' . '/src/Base/BaseSessionHandler.php',
        'Luminova\\Boot' => __DIR__ . '/../..' . '/src/Boot.php',
        'Luminova\\Builder\\Document' => __DIR__ . '/../..' . '/src/Builder/Document.php',
        'Luminova\\Builder\\Inputs' => __DIR__ . '/../..' . '/src/Builder/Inputs.php',
        'Luminova\\Builder\\Xhtml' => __DIR__ . '/../..' . '/src/Builder/Xhtml.php',
        'Luminova\\Cache\\FileCache' => __DIR__ . '/../..' . '/src/Cache/FileCache.php',
        'Luminova\\Cache\\MemoryCache' => __DIR__ . '/../..' . '/src/Cache/MemoryCache.php',
        'Luminova\\Cache\\RedisCache' => __DIR__ . '/../..' . '/src/Cache/RedisCache.php',
        'Luminova\\Cache\\TemplateCache' => __DIR__ . '/../..' . '/src/Cache/TemplateCache.php',
        'Luminova\\Command\\Auth\\Handler' => __DIR__ . '/../..' . '/src/Command/Auth/Handler.php',
        'Luminova\\Command\\Auth\\Session' => __DIR__ . '/../..' . '/src/Command/Auth/Session.php',
        'Luminova\\Command\\Console' => __DIR__ . '/../..' . '/src/Command/Console.php',
        'Luminova\\Command\\Novakit\\Builder' => __DIR__ . '/../..' . '/src/Command/Novakit/Builder.php',
        'Luminova\\Command\\Novakit\\ClearWritable' => __DIR__ . '/../..' . '/src/Command/Novakit/ClearWritable.php',
        'Luminova\\Command\\Novakit\\Commands' => __DIR__ . '/../..' . '/src/Command/Novakit/Commands.php',
        'Luminova\\Command\\Novakit\\Context' => __DIR__ . '/../..' . '/src/Command/Novakit/Context.php',
        'Luminova\\Command\\Novakit\\CronJobs' => __DIR__ . '/../..' . '/src/Command/Novakit/CronJobs.php',
        'Luminova\\Command\\Novakit\\Database' => __DIR__ . '/../..' . '/src/Command/Novakit/Database.php',
        'Luminova\\Command\\Novakit\\Generators' => __DIR__ . '/../..' . '/src/Command/Novakit/Generators.php',
        'Luminova\\Command\\Novakit\\Lists' => __DIR__ . '/../..' . '/src/Command/Novakit/Lists.php',
        'Luminova\\Command\\Novakit\\Logs' => __DIR__ . '/../..' . '/src/Command/Novakit/Logs.php',
        'Luminova\\Command\\Novakit\\Server' => __DIR__ . '/../..' . '/src/Command/Novakit/Server.php',
        'Luminova\\Command\\Novakit\\System' => __DIR__ . '/../..' . '/src/Command/Novakit/System.php',
        'Luminova\\Command\\Novakit\\SystemHelp' => __DIR__ . '/../..' . '/src/Command/Novakit/SystemHelp.php',
        'Luminova\\Command\\Remote' => __DIR__ . '/../..' . '/src/Command/Remote.php',
        'Luminova\\Command\\Terminal' => __DIR__ . '/../..' . '/src/Command/Terminal.php',
        'Luminova\\Command\\Utils\\Color' => __DIR__ . '/../..' . '/src/Command/Utils/Color.php',
        'Luminova\\Command\\Utils\\Image' => __DIR__ . '/../..' . '/src/Command/Utils/Image.php',
        'Luminova\\Command\\Utils\\Text' => __DIR__ . '/../..' . '/src/Command/Utils/Text.php',
        'Luminova\\Composer\\BaseComposer' => __DIR__ . '/../..' . '/src/Composer/BaseComposer.php',
        'Luminova\\Composer\\Builder' => __DIR__ . '/../..' . '/src/Composer/Builder.php',
        'Luminova\\Composer\\Updater' => __DIR__ . '/../..' . '/src/Composer/Updater.php',
        'Luminova\\Config\\Environment' => __DIR__ . '/../..' . '/src/Config/Environment.php',
        'Luminova\\Cookies\\Cookie' => __DIR__ . '/../..' . '/src/Cookies/Cookie.php',
        'Luminova\\Cookies\\CookieFileJar' => __DIR__ . '/../..' . '/src/Cookies/CookieFileJar.php',
        'Luminova\\Cookies\\CookieTrait' => __DIR__ . '/../..' . '/src/Cookies/CookieTrait.php',
        'Luminova\\Core\\CoreApplication' => __DIR__ . '/../..' . '/src/Core/CoreApplication.php',
        'Luminova\\Core\\CoreCronTasks' => __DIR__ . '/../..' . '/src/Core/CoreCronTasks.php',
        'Luminova\\Core\\CoreDatabase' => __DIR__ . '/../..' . '/src/Core/CoreDatabase.php',
        'Luminova\\Core\\CoreFunction' => __DIR__ . '/../..' . '/src/Core/CoreFunction.php',
        'Luminova\\Core\\CoreServices' => __DIR__ . '/../..' . '/src/Core/CoreServices.php',
        'Luminova\\Database\\Alter' => __DIR__ . '/../..' . '/src/Database/Alter.php',
        'Luminova\\Database\\Builder' => __DIR__ . '/../..' . '/src/Database/Builder.php',
        'Luminova\\Database\\Connection' => __DIR__ . '/../..' . '/src/Database/Connection.php',
        'Luminova\\Database\\Drivers\\MysqliDriver' => __DIR__ . '/../..' . '/src/Database/Drivers/MysqliDriver.php',
        'Luminova\\Database\\Drivers\\PdoDriver' => __DIR__ . '/../..' . '/src/Database/Drivers/PdoDriver.php',
        'Luminova\\Database\\Manager' => __DIR__ . '/../..' . '/src/Database/Manager.php',
        'Luminova\\Database\\Migration' => __DIR__ . '/../..' . '/src/Database/Migration.php',
        'Luminova\\Database\\Schema' => __DIR__ . '/../..' . '/src/Database/Schema.php',
        'Luminova\\Database\\Seeder' => __DIR__ . '/../..' . '/src/Database/Seeder.php',
        'Luminova\\Database\\Table' => __DIR__ . '/../..' . '/src/Database/Table.php',
        'Luminova\\Database\\TableTrait' => __DIR__ . '/../..' . '/src/Database/TableTrait.php',
        'Luminova\\Debugger\\PHPStanRules' => __DIR__ . '/../..' . '/src/Debugger/PHPStanRules.php',
        'Luminova\\Debugger\\Performance' => __DIR__ . '/../..' . '/src/Debugger/Performance.php',
        'Luminova\\Debugger\\PhpCsFixer' => __DIR__ . '/../..' . '/src/Debugger/PhpCsFixer.php',
        'Luminova\\Debugger\\Tracer' => __DIR__ . '/../..' . '/src/Debugger/Tracer.php',
        'Luminova\\Email\\Clients\\NovaMailer' => __DIR__ . '/../..' . '/src/Email/Clients/NovaMailer.php',
        'Luminova\\Email\\Clients\\PHPMailer' => __DIR__ . '/../..' . '/src/Email/Clients/PHPMailer.php',
        'Luminova\\Email\\Clients\\SwiftMailer' => __DIR__ . '/../..' . '/src/Email/Clients/SwiftMailer.php',
        'Luminova\\Email\\Mailer' => __DIR__ . '/../..' . '/src/Email/Mailer.php',
        'Luminova\\Errors\\ErrorHandler' => __DIR__ . '/../..' . '/src/Errors/ErrorHandler.php',
        'Luminova\\Exceptions\\AppException' => __DIR__ . '/../..' . '/src/Exceptions/AppException.php',
        'Luminova\\Exceptions\\BadMethodCallException' => __DIR__ . '/../..' . '/src/Exceptions/BadMethodCallException.php',
        'Luminova\\Exceptions\\CacheException' => __DIR__ . '/../..' . '/src/Exceptions/CacheException.php',
        'Luminova\\Exceptions\\ClassException' => __DIR__ . '/../..' . '/src/Exceptions/ClassException.php',
        'Luminova\\Exceptions\\CookieException' => __DIR__ . '/../..' . '/src/Exceptions/CookieException.php',
        'Luminova\\Exceptions\\DatabaseException' => __DIR__ . '/../..' . '/src/Exceptions/DatabaseException.php',
        'Luminova\\Exceptions\\DatabaseLimitException' => __DIR__ . '/../..' . '/src/Exceptions/DatabaseLimitException.php',
        'Luminova\\Exceptions\\DateTimeException' => __DIR__ . '/../..' . '/src/Exceptions/DateTimeException.php',
        'Luminova\\Exceptions\\EncryptionException' => __DIR__ . '/../..' . '/src/Exceptions/EncryptionException.php',
        'Luminova\\Exceptions\\ErrorException' => __DIR__ . '/../..' . '/src/Exceptions/ErrorException.php',
        'Luminova\\Exceptions\\FileException' => __DIR__ . '/../..' . '/src/Exceptions/FileException.php',
        'Luminova\\Exceptions\\Http\\ClientException' => __DIR__ . '/../..' . '/src/Exceptions/Http/ClientException.php',
        'Luminova\\Exceptions\\Http\\ConnectException' => __DIR__ . '/../..' . '/src/Exceptions/Http/ConnectException.php',
        'Luminova\\Exceptions\\Http\\RequestException' => __DIR__ . '/../..' . '/src/Exceptions/Http/RequestException.php',
        'Luminova\\Exceptions\\Http\\ServerException' => __DIR__ . '/../..' . '/src/Exceptions/Http/ServerException.php',
        'Luminova\\Exceptions\\InvalidArgumentException' => __DIR__ . '/../..' . '/src/Exceptions/InvalidArgumentException.php',
        'Luminova\\Exceptions\\InvalidException' => __DIR__ . '/../..' . '/src/Exceptions/InvalidException.php',
        'Luminova\\Exceptions\\InvalidObjectException' => __DIR__ . '/../..' . '/src/Exceptions/InvalidObjectException.php',
        'Luminova\\Exceptions\\JsonException' => __DIR__ . '/../..' . '/src/Exceptions/JsonException.php',
        'Luminova\\Exceptions\\LogicException' => __DIR__ . '/../..' . '/src/Exceptions/LogicException.php',
        'Luminova\\Exceptions\\MailerException' => __DIR__ . '/../..' . '/src/Exceptions/MailerException.php',
        'Luminova\\Exceptions\\NotFoundException' => __DIR__ . '/../..' . '/src/Exceptions/NotFoundException.php',
        'Luminova\\Exceptions\\RouterException' => __DIR__ . '/../..' . '/src/Exceptions/RouterException.php',
        'Luminova\\Exceptions\\RuntimeException' => __DIR__ . '/../..' . '/src/Exceptions/RuntimeException.php',
        'Luminova\\Exceptions\\SecurityException' => __DIR__ . '/../..' . '/src/Exceptions/SecurityException.php',
        'Luminova\\Exceptions\\StorageException' => __DIR__ . '/../..' . '/src/Exceptions/StorageException.php',
        'Luminova\\Exceptions\\ValidationException' => __DIR__ . '/../..' . '/src/Exceptions/ValidationException.php',
        'Luminova\\Exceptions\\ViewNotFoundException' => __DIR__ . '/../..' . '/src/Exceptions/ViewNotFoundException.php',
        'Luminova\\Ftp\\FTP' => __DIR__ . '/../..' . '/src/Ftp/FTP.php',
        'Luminova\\Functions\\Escape' => __DIR__ . '/../..' . '/src/Functions/Escape.php',
        'Luminova\\Functions\\Func' => __DIR__ . '/../..' . '/src/Functions/Func.php',
        'Luminova\\Functions\\IP' => __DIR__ . '/../..' . '/src/Functions/IP.php',
        'Luminova\\Functions\\Maths' => __DIR__ . '/../..' . '/src/Functions/Maths.php',
        'Luminova\\Functions\\Normalizer' => __DIR__ . '/../..' . '/src/Functions/Normalizer.php',
        'Luminova\\Functions\\Tor' => __DIR__ . '/../..' . '/src/Functions/Tor.php',
        'Luminova\\Http\\Client\\Curl' => __DIR__ . '/../..' . '/src/Http/Client/Curl.php',
        'Luminova\\Http\\Client\\Guzzle' => __DIR__ . '/../..' . '/src/Http/Client/Guzzle.php',
        'Luminova\\Http\\Encoder' => __DIR__ . '/../..' . '/src/Http/Encoder.php',
        'Luminova\\Http\\File' => __DIR__ . '/../..' . '/src/Http/File.php',
        'Luminova\\Http\\Header' => __DIR__ . '/../..' . '/src/Http/Header.php',
        'Luminova\\Http\\HttpCode' => __DIR__ . '/../..' . '/src/Http/HttpCode.php',
        'Luminova\\Http\\HttpServer' => __DIR__ . '/../..' . '/src/Http/HttpServer.php',
        'Luminova\\Http\\HttpServerTrait' => __DIR__ . '/../..' . '/src/Http/HttpServerTrait.php',
        'Luminova\\Http\\Message\\Response' => __DIR__ . '/../..' . '/src/Http/Message/Response.php',
        'Luminova\\Http\\Network' => __DIR__ . '/../..' . '/src/Http/Network.php',
        'Luminova\\Http\\Request' => __DIR__ . '/../..' . '/src/Http/Request.php',
        'Luminova\\Http\\Server' => __DIR__ . '/../..' . '/src/Http/Server.php',
        'Luminova\\Http\\Uri' => __DIR__ . '/../..' . '/src/Http/Uri.php',
        'Luminova\\Http\\UserAgent' => __DIR__ . '/../..' . '/src/Http/UserAgent.php',
        'Luminova\\Interface\\AiInterface' => __DIR__ . '/../..' . '/src/Interface/AiInterface.php',
        'Luminova\\Interface\\AuthenticatorInterface' => __DIR__ . '/../..' . '/src/Interface/AuthenticatorInterface.php',
        'Luminova\\Interface\\CallableInterface' => __DIR__ . '/../..' . '/src/Interface/CallableInterface.php',
        'Luminova\\Interface\\ClientInterface' => __DIR__ . '/../..' . '/src/Interface/ClientInterface.php',
        'Luminova\\Interface\\ConnInterface' => __DIR__ . '/../..' . '/src/Interface/ConnInterface.php',
        'Luminova\\Interface\\CookieInterface' => __DIR__ . '/../..' . '/src/Interface/CookieInterface.php',
        'Luminova\\Interface\\CookieJarInterface' => __DIR__ . '/../..' . '/src/Interface/CookieJarInterface.php',
        'Luminova\\Interface\\DatabaseInterface' => __DIR__ . '/../..' . '/src/Interface/DatabaseInterface.php',
        'Luminova\\Interface\\EncryptionInterface' => __DIR__ . '/../..' . '/src/Interface/EncryptionInterface.php',
        'Luminova\\Interface\\ErrorHandlerInterface' => __DIR__ . '/../..' . '/src/Interface/ErrorHandlerInterface.php',
        'Luminova\\Interface\\ExceptionInterface' => __DIR__ . '/../..' . '/src/Interface/ExceptionInterface.php',
        'Luminova\\Interface\\HttpRequestInterface' => __DIR__ . '/../..' . '/src/Interface/HttpRequestInterface.php',
        'Luminova\\Interface\\LazyInterface' => __DIR__ . '/../..' . '/src/Interface/LazyInterface.php',
        'Luminova\\Interface\\MailerInterface' => __DIR__ . '/../..' . '/src/Interface/MailerInterface.php',
        'Luminova\\Interface\\NetworkInterface' => __DIR__ . '/../..' . '/src/Interface/NetworkInterface.php',
        'Luminova\\Interface\\PromiseInterface' => __DIR__ . '/../..' . '/src/Interface/PromiseInterface.php',
        'Luminova\\Interface\\ResponseInterface' => __DIR__ . '/../..' . '/src/Interface/ResponseInterface.php',
        'Luminova\\Interface\\RouterInterface' => __DIR__ . '/../..' . '/src/Interface/RouterInterface.php',
        'Luminova\\Interface\\ServicesInterface' => __DIR__ . '/../..' . '/src/Interface/ServicesInterface.php',
        'Luminova\\Interface\\SessionManagerInterface' => __DIR__ . '/../..' . '/src/Interface/SessionManagerInterface.php',
        'Luminova\\Interface\\ValidationInterface' => __DIR__ . '/../..' . '/src/Interface/ValidationInterface.php',
        'Luminova\\Interface\\ViewResponseInterface' => __DIR__ . '/../..' . '/src/Interface/ViewResponseInterface.php',
        'Luminova\\Languages\\Translator' => __DIR__ . '/../..' . '/src/Languages/Translator.php',
        'Luminova\\Library\\Modules' => __DIR__ . '/../..' . '/src/Library/Modules.php',
        'Luminova\\Logger\\LogLevel' => __DIR__ . '/../..' . '/src/Logger/LogLevel.php',
        'Luminova\\Logger\\Logger' => __DIR__ . '/../..' . '/src/Logger/Logger.php',
        'Luminova\\Logger\\LoggerAware' => __DIR__ . '/../..' . '/src/Logger/LoggerAware.php',
        'Luminova\\Logger\\NovaLogger' => __DIR__ . '/../..' . '/src/Logger/NovaLogger.php',
        'Luminova\\Notifications\\Firebase\\Database' => __DIR__ . '/../..' . '/src/Notifications/Firebase/Database.php',
        'Luminova\\Notifications\\Firebase\\Notification' => __DIR__ . '/../..' . '/src/Notifications/Firebase/Notification.php',
        'Luminova\\Notifications\\Models\\Message' => __DIR__ . '/../..' . '/src/Notifications/Models/Message.php',
        'Luminova\\Optimization\\Minification' => __DIR__ . '/../..' . '/src/Optimization/Minification.php',
        'Luminova\\Routing\\Prefix' => __DIR__ . '/../..' . '/src/Routing/Prefix.php',
        'Luminova\\Routing\\Router' => __DIR__ . '/../..' . '/src/Routing/Router.php',
        'Luminova\\Routing\\Segments' => __DIR__ . '/../..' . '/src/Routing/Segments.php',
        'Luminova\\Security\\Authenticator\\Google' => __DIR__ . '/../..' . '/src/Security/Authenticator/Google.php',
        'Luminova\\Security\\Crypter' => __DIR__ . '/../..' . '/src/Security/Crypter.php',
        'Luminova\\Security\\Csrf' => __DIR__ . '/../..' . '/src/Security/Csrf.php',
        'Luminova\\Security\\Encryption\\OpenSSL' => __DIR__ . '/../..' . '/src/Security/Encryption/OpenSSL.php',
        'Luminova\\Security\\Encryption\\Sodium' => __DIR__ . '/../..' . '/src/Security/Encryption/Sodium.php',
        'Luminova\\Security\\JWTAuth' => __DIR__ . '/../..' . '/src/Security/JWTAuth.php',
        'Luminova\\Security\\TOTP' => __DIR__ . '/../..' . '/src/Security/TOTP.php',
        'Luminova\\Security\\Validation' => __DIR__ . '/../..' . '/src/Security/Validation.php',
        'Luminova\\Seo\\Schema' => __DIR__ . '/../..' . '/src/Seo/Schema.php',
        'Luminova\\Seo\\Sitemap' => __DIR__ . '/../..' . '/src/Seo/Sitemap.php',
        'Luminova\\Sessions\\Handlers\\ArrayHandler' => __DIR__ . '/../..' . '/src/Sessions/Handlers/ArrayHandler.php',
        'Luminova\\Sessions\\Handlers\\Database' => __DIR__ . '/../..' . '/src/Sessions/Handlers/Database.php',
        'Luminova\\Sessions\\Handlers\\Filesystem' => __DIR__ . '/../..' . '/src/Sessions/Handlers/Filesystem.php',
        'Luminova\\Sessions\\Managers\\Cookie' => __DIR__ . '/../..' . '/src/Sessions/Managers/Cookie.php',
        'Luminova\\Sessions\\Managers\\Session' => __DIR__ . '/../..' . '/src/Sessions/Managers/Session.php',
        'Luminova\\Sessions\\Session' => __DIR__ . '/../..' . '/src/Sessions/Session.php',
        'Luminova\\Storages\\Adapters\\Adapters' => __DIR__ . '/../..' . '/src/Storages/Adapters/Adapters.php',
        'Luminova\\Storages\\Archive' => __DIR__ . '/../..' . '/src/Storages/Archive.php',
        'Luminova\\Storages\\FileDelivery' => __DIR__ . '/../..' . '/src/Storages/FileDelivery.php',
        'Luminova\\Storages\\FileManager' => __DIR__ . '/../..' . '/src/Storages/FileManager.php',
        'Luminova\\Storages\\Storage' => __DIR__ . '/../..' . '/src/Storages/Storage.php',
        'Luminova\\Storages\\Stream' => __DIR__ . '/../..' . '/src/Storages/Stream.php',
        'Luminova\\Storages\\StreamWrapper' => __DIR__ . '/../..' . '/src/Storages/StreamWrapper.php',
        'Luminova\\Storages\\Uploader' => __DIR__ . '/../..' . '/src/Storages/Uploader.php',
        'Luminova\\Template\\Layout' => __DIR__ . '/../..' . '/src/Template/Layout.php',
        'Luminova\\Template\\Response' => __DIR__ . '/../..' . '/src/Template/Response.php',
        'Luminova\\Template\\Smarty' => __DIR__ . '/../..' . '/src/Template/Smarty.php',
        'Luminova\\Template\\Twig' => __DIR__ . '/../..' . '/src/Template/Twig.php',
        'Luminova\\Template\\View' => __DIR__ . '/../..' . '/src/Template/View.php',
        'Luminova\\Time\\CronInterval' => __DIR__ . '/../..' . '/src/Time/CronInterval.php',
        'Luminova\\Time\\Task' => __DIR__ . '/../..' . '/src/Time/Task.php',
        'Luminova\\Time\\Time' => __DIR__ . '/../..' . '/src/Time/Time.php',
        'Luminova\\Time\\Timestamp' => __DIR__ . '/../..' . '/src/Time/Timestamp.php',
        'Luminova\\Utils\\Async' => __DIR__ . '/../..' . '/src/Utils/Async.php',
        'Luminova\\Utils\\Event' => __DIR__ . '/../..' . '/src/Utils/Event.php',
        'Luminova\\Utils\\Interval' => __DIR__ . '/../..' . '/src/Utils/Interval.php',
        'Luminova\\Utils\\LazyObject' => __DIR__ . '/../..' . '/src/Utils/LazyObject.php',
        'Luminova\\Utils\\Pipeline' => __DIR__ . '/../..' . '/src/Utils/Pipeline.php',
        'Luminova\\Utils\\Process' => __DIR__ . '/../..' . '/src/Utils/Process.php',
        'Luminova\\Utils\\Promise\\FulfilledPromise' => __DIR__ . '/../..' . '/src/Utils/Promise/FulfilledPromise.php',
        'Luminova\\Utils\\Promise\\Helper' => __DIR__ . '/../..' . '/src/Utils/Promise/Helper.php',
        'Luminova\\Utils\\Promise\\Promise' => __DIR__ . '/../..' . '/src/Utils/Promise/Promise.php',
        'Luminova\\Utils\\Promise\\Queue' => __DIR__ . '/../..' . '/src/Utils/Promise/Queue.php',
        'Luminova\\Utils\\Promise\\RejectedPromise' => __DIR__ . '/../..' . '/src/Utils/Promise/RejectedPromise.php',
        'Luminova\\Utils\\Queue' => __DIR__ . '/../..' . '/src/Utils/Queue.php',
        'Luminova\\Utils\\WeakReference' => __DIR__ . '/../..' . '/src/Utils/WeakReference.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit9bbe5f33f53702290b6f40e5fff6e1ff::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit9bbe5f33f53702290b6f40e5fff6e1ff::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit9bbe5f33f53702290b6f40e5fff6e1ff::$classMap;

        }, null, ClassLoader::class);
    }
}
