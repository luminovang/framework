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
        'Luminova\\Application\\Factory' => __DIR__ . '/../..' . '/src/Application/Factory.php',
        'Luminova\\Application\\FileSystem' => __DIR__ . '/../..' . '/src/Application/FileSystem.php',
        'Luminova\\Application\\Foundation' => __DIR__ . '/../..' . '/src/Application/Foundation.php',
        'Luminova\\Application\\Functions' => __DIR__ . '/../..' . '/src/Application/Functions.php',
        'Luminova\\Application\\Services' => __DIR__ . '/../..' . '/src/Application/Services.php',
        'Luminova\\Arrays\\ArrayCountable' => __DIR__ . '/../..' . '/src/Arrays/ArrayCountable.php',
        'Luminova\\Arrays\\ArrayInput' => __DIR__ . '/../..' . '/src/Arrays/ArrayInput.php',
        'Luminova\\Arrays\\Arrays' => __DIR__ . '/../..' . '/src/Arrays/Arrays.php',
        'Luminova\\Base\\BaseApplication' => __DIR__ . '/../..' . '/src/Base/BaseApplication.php',
        'Luminova\\Base\\BaseCommand' => __DIR__ . '/../..' . '/src/Base/BaseCommand.php',
        'Luminova\\Base\\BaseConfig' => __DIR__ . '/../..' . '/src/Base/BaseConfig.php',
        'Luminova\\Base\\BaseConsole' => __DIR__ . '/../..' . '/src/Base/BaseConsole.php',
        'Luminova\\Base\\BaseController' => __DIR__ . '/../..' . '/src/Base/BaseController.php',
        'Luminova\\Base\\BaseDatabase' => __DIR__ . '/../..' . '/src/Base/BaseDatabase.php',
        'Luminova\\Base\\BaseException' => __DIR__ . '/../..' . '/src/Base/BaseException.php',
        'Luminova\\Base\\BaseFiles' => __DIR__ . '/../..' . '/src/Base/BaseFiles.php',
        'Luminova\\Base\\BaseFunction' => __DIR__ . '/../..' . '/src/Base/BaseFunction.php',
        'Luminova\\Base\\BaseMailer' => __DIR__ . '/../..' . '/src/Base/BaseMailer.php',
        'Luminova\\Base\\BaseModel' => __DIR__ . '/../..' . '/src/Base/BaseModel.php',
        'Luminova\\Base\\BaseServices' => __DIR__ . '/../..' . '/src/Base/BaseServices.php',
        'Luminova\\Base\\BaseViewController' => __DIR__ . '/../..' . '/src/Base/BaseViewController.php',
        'Luminova\\Builder\\Csp' => __DIR__ . '/../..' . '/src/Builder/Csp.php',
        'Luminova\\Builder\\Document' => __DIR__ . '/../..' . '/src/Builder/Document.php',
        'Luminova\\Builder\\Html' => __DIR__ . '/../..' . '/src/Builder/Html.php',
        'Luminova\\Builder\\Inputs' => __DIR__ . '/../..' . '/src/Builder/Inputs.php',
        'Luminova\\Cache\\Cache' => __DIR__ . '/../..' . '/src/Cache/Cache.php',
        'Luminova\\Cache\\FileCache' => __DIR__ . '/../..' . '/src/Cache/FileCache.php',
        'Luminova\\Cache\\MemoryCache' => __DIR__ . '/../..' . '/src/Cache/MemoryCache.php',
        'Luminova\\Cache\\PageMinifier' => __DIR__ . '/../..' . '/src/Cache/PageMinifier.php',
        'Luminova\\Cache\\PageViewCache' => __DIR__ . '/../..' . '/src/Cache/PageViewCache.php',
        'Luminova\\Command\\Colors' => __DIR__ . '/../..' . '/src/Command/Colors.php',
        'Luminova\\Command\\Console' => __DIR__ . '/../..' . '/src/Command/Console.php',
        'Luminova\\Command\\Executor' => __DIR__ . '/../..' . '/src/Command/Executor.php',
        'Luminova\\Command\\Novakit\\Builder' => __DIR__ . '/../..' . '/src/Command/Novakit/Builder.php',
        'Luminova\\Command\\Novakit\\Commands' => __DIR__ . '/../..' . '/src/Command/Novakit/Commands.php',
        'Luminova\\Command\\Novakit\\Context' => __DIR__ . '/../..' . '/src/Command/Novakit/Context.php',
        'Luminova\\Command\\Novakit\\Database' => __DIR__ . '/../..' . '/src/Command/Novakit/Database.php',
        'Luminova\\Command\\Novakit\\Generators' => __DIR__ . '/../..' . '/src/Command/Novakit/Generators.php',
        'Luminova\\Command\\Novakit\\Help' => __DIR__ . '/../..' . '/src/Command/Novakit/Help.php',
        'Luminova\\Command\\Novakit\\Lists' => __DIR__ . '/../..' . '/src/Command/Novakit/Lists.php',
        'Luminova\\Command\\Novakit\\Server' => __DIR__ . '/../..' . '/src/Command/Novakit/Server.php',
        'Luminova\\Command\\Novakit\\System' => __DIR__ . '/../..' . '/src/Command/Novakit/System.php',
        'Luminova\\Command\\Terminal' => __DIR__ . '/../..' . '/src/Command/Terminal.php',
        'Luminova\\Command\\TextUtils' => __DIR__ . '/../..' . '/src/Command/TextUtils.php',
        'Luminova\\Composer\\BaseComposer' => __DIR__ . '/../..' . '/src/Composer/BaseComposer.php',
        'Luminova\\Composer\\Builder' => __DIR__ . '/../..' . '/src/Composer/Builder.php',
        'Luminova\\Composer\\Updater' => __DIR__ . '/../..' . '/src/Composer/Updater.php',
        'Luminova\\Config\\DotEnv' => __DIR__ . '/../..' . '/src/Config/DotEnv.php',
        'Luminova\\Config\\PHPStanRules' => __DIR__ . '/../..' . '/src/Config/PHPStanRules.php',
        'Luminova\\Config\\SystemPaths' => __DIR__ . '/../..' . '/src/Config/SystemPaths.php',
        'Luminova\\Cookies\\Cookie' => __DIR__ . '/../..' . '/src/Cookies/Cookie.php',
        'Luminova\\Database\\Builder' => __DIR__ . '/../..' . '/src/Database/Builder.php',
        'Luminova\\Database\\Conn\\mysqliConn' => __DIR__ . '/../..' . '/src/Database/Conn/mysqliConn.php',
        'Luminova\\Database\\Conn\\pdoConn' => __DIR__ . '/../..' . '/src/Database/Conn/pdoConn.php',
        'Luminova\\Database\\Connection' => __DIR__ . '/../..' . '/src/Database/Connection.php',
        'Luminova\\Database\\Drivers\\MySqliDriver' => __DIR__ . '/../..' . '/src/Database/Drivers/MySqliDriver.php',
        'Luminova\\Database\\Drivers\\PdoDriver' => __DIR__ . '/../..' . '/src/Database/Drivers/PdoDriver.php',
        'Luminova\\Database\\Manager' => __DIR__ . '/../..' . '/src/Database/Manager.php',
        'Luminova\\Database\\Scheme' => __DIR__ . '/../..' . '/src/Database/Scheme.php',
        'Luminova\\Debugger\\PHPStanRules' => __DIR__ . '/../..' . '/src/Debugger/PHPStanRules.php',
        'Luminova\\Debugger\\Tracer' => __DIR__ . '/../..' . '/src/Debugger/Tracer.php',
        'Luminova\\Email\\Clients\\NovaMailer' => __DIR__ . '/../..' . '/src/Email/Clients/NovaMailer.php',
        'Luminova\\Email\\Clients\\PHPMailer' => __DIR__ . '/../..' . '/src/Email/Clients/PHPMailer.php',
        'Luminova\\Email\\Clients\\SwiftMailer' => __DIR__ . '/../..' . '/src/Email/Clients/SwiftMailer.php',
        'Luminova\\Email\\Helpers\\Helper' => __DIR__ . '/../..' . '/src/Email/Helpers/Helper.php',
        'Luminova\\Email\\Mailer' => __DIR__ . '/../..' . '/src/Email/Mailer.php',
        'Luminova\\Errors\\Error' => __DIR__ . '/../..' . '/src/Errors/Error.php',
        'Luminova\\Errors\\ErrorStack' => __DIR__ . '/../..' . '/src/Errors/ErrorStack.php',
        'Luminova\\Exceptions\\AppException' => __DIR__ . '/../..' . '/src/Exceptions/AppException.php',
        'Luminova\\Exceptions\\BadMethodCallException' => __DIR__ . '/../..' . '/src/Exceptions/BadMethodCallException.php',
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
        'Luminova\\Exceptions\\MailerException' => __DIR__ . '/../..' . '/src/Exceptions/MailerException.php',
        'Luminova\\Exceptions\\NotFoundException' => __DIR__ . '/../..' . '/src/Exceptions/NotFoundException.php',
        'Luminova\\Exceptions\\RouterException' => __DIR__ . '/../..' . '/src/Exceptions/RouterException.php',
        'Luminova\\Exceptions\\RuntimeException' => __DIR__ . '/../..' . '/src/Exceptions/RuntimeException.php',
        'Luminova\\Exceptions\\SecurityException' => __DIR__ . '/../..' . '/src/Exceptions/SecurityException.php',
        'Luminova\\Exceptions\\StorageException' => __DIR__ . '/../..' . '/src/Exceptions/StorageException.php',
        'Luminova\\Exceptions\\ValidationException' => __DIR__ . '/../..' . '/src/Exceptions/ValidationException.php',
        'Luminova\\Exceptions\\ViewNotFoundException' => __DIR__ . '/../..' . '/src/Exceptions/ViewNotFoundException.php',
        'Luminova\\Functions\\Escaper' => __DIR__ . '/../..' . '/src/Functions/Escaper.php',
        'Luminova\\Functions\\IPAddress' => __DIR__ . '/../..' . '/src/Functions/IPAddress.php',
        'Luminova\\Functions\\Maths' => __DIR__ . '/../..' . '/src/Functions/Maths.php',
        'Luminova\\Functions\\Normalizer' => __DIR__ . '/../..' . '/src/Functions/Normalizer.php',
        'Luminova\\Functions\\TorDetector' => __DIR__ . '/../..' . '/src/Functions/TorDetector.php',
        'Luminova\\Http\\Client\\Curl' => __DIR__ . '/../..' . '/src/Http/Client/Curl.php',
        'Luminova\\Http\\Client\\Guzzle' => __DIR__ . '/../..' . '/src/Http/Client/Guzzle.php',
        'Luminova\\Http\\Encoder' => __DIR__ . '/../..' . '/src/Http/Encoder.php',
        'Luminova\\Http\\File' => __DIR__ . '/../..' . '/src/Http/File.php',
        'Luminova\\Http\\Header' => __DIR__ . '/../..' . '/src/Http/Header.php',
        'Luminova\\Http\\HttpCode' => __DIR__ . '/../..' . '/src/Http/HttpCode.php',
        'Luminova\\Http\\Message\\Response' => __DIR__ . '/../..' . '/src/Http/Message/Response.php',
        'Luminova\\Http\\Network' => __DIR__ . '/../..' . '/src/Http/Network.php',
        'Luminova\\Http\\Request' => __DIR__ . '/../..' . '/src/Http/Request.php',
        'Luminova\\Http\\Server' => __DIR__ . '/../..' . '/src/Http/Server.php',
        'Luminova\\Http\\UserAgent' => __DIR__ . '/../..' . '/src/Http/UserAgent.php',
        'Luminova\\Interface\\AsyncClientInterface' => __DIR__ . '/../..' . '/src/Interface/AsyncClientInterface.php',
        'Luminova\\Interface\\ConnInterface' => __DIR__ . '/../..' . '/src/Interface/ConnInterface.php',
        'Luminova\\Interface\\CookieInterface' => __DIR__ . '/../..' . '/src/Interface/CookieInterface.php',
        'Luminova\\Interface\\DatabaseInterface' => __DIR__ . '/../..' . '/src/Interface/DatabaseInterface.php',
        'Luminova\\Interface\\EncryptionInterface' => __DIR__ . '/../..' . '/src/Interface/EncryptionInterface.php',
        'Luminova\\Interface\\ExceptionInterface' => __DIR__ . '/../..' . '/src/Interface/ExceptionInterface.php',
        'Luminova\\Interface\\HttpClientInterface' => __DIR__ . '/../..' . '/src/Interface/HttpClientInterface.php',
        'Luminova\\Interface\\MailerInterface' => __DIR__ . '/../..' . '/src/Interface/MailerInterface.php',
        'Luminova\\Interface\\NetworkClientInterface' => __DIR__ . '/../..' . '/src/Interface/NetworkClientInterface.php',
        'Luminova\\Interface\\NetworkInterface' => __DIR__ . '/../..' . '/src/Interface/NetworkInterface.php',
        'Luminova\\Interface\\ServicesInterface' => __DIR__ . '/../..' . '/src/Interface/ServicesInterface.php',
        'Luminova\\Interface\\SessionManagerInterface' => __DIR__ . '/../..' . '/src/Interface/SessionManagerInterface.php',
        'Luminova\\Interface\\ValidationInterface' => __DIR__ . '/../..' . '/src/Interface/ValidationInterface.php',
        'Luminova\\Languages\\Translator' => __DIR__ . '/../..' . '/src/Languages/Translator.php',
        'Luminova\\Library\\Modules' => __DIR__ . '/../..' . '/src/Library/Modules.php',
        'Luminova\\Logger\\Logger' => __DIR__ . '/../..' . '/src/Logger/Logger.php',
        'Luminova\\Logger\\LoggerAware' => __DIR__ . '/../..' . '/src/Logger/LoggerAware.php',
        'Luminova\\Logger\\NovaLogger' => __DIR__ . '/../..' . '/src/Logger/NovaLogger.php',
        'Luminova\\Models\\PushMessage' => __DIR__ . '/../..' . '/src/Models/PushMessage.php',
        'Luminova\\Notifications\\FirebasePusher' => __DIR__ . '/../..' . '/src/Notifications/FirebasePusher.php',
        'Luminova\\Notifications\\FirebaseRealtime' => __DIR__ . '/../..' . '/src/Notifications/FirebaseRealtime.php',
        'Luminova\\Routing\\Bootstrap' => __DIR__ . '/../..' . '/src/Routing/Bootstrap.php',
        'Luminova\\Routing\\Router' => __DIR__ . '/../..' . '/src/Routing/Router.php',
        'Luminova\\Routing\\Segments' => __DIR__ . '/../..' . '/src/Routing/Segments.php',
        'Luminova\\Security\\Crypter' => __DIR__ . '/../..' . '/src/Security/Crypter.php',
        'Luminova\\Security\\Csrf' => __DIR__ . '/../..' . '/src/Security/Csrf.php',
        'Luminova\\Security\\Encryption\\OpenSSL' => __DIR__ . '/../..' . '/src/Security/Encryption/OpenSSL.php',
        'Luminova\\Security\\Encryption\\Sodium' => __DIR__ . '/../..' . '/src/Security/Encryption/Sodium.php',
        'Luminova\\Security\\InputValidator' => __DIR__ . '/../..' . '/src/Security/InputValidator.php',
        'Luminova\\Seo\\Schema' => __DIR__ . '/../..' . '/src/Seo/Schema.php',
        'Luminova\\Seo\\Sitemap' => __DIR__ . '/../..' . '/src/Seo/Sitemap.php',
        'Luminova\\Sessions\\CookieManager' => __DIR__ . '/../..' . '/src/Sessions/CookieManager.php',
        'Luminova\\Sessions\\Session' => __DIR__ . '/../..' . '/src/Sessions/Session.php',
        'Luminova\\Sessions\\SessionManager' => __DIR__ . '/../..' . '/src/Sessions/SessionManager.php',
        'Luminova\\Storages\\Storage' => __DIR__ . '/../..' . '/src/Storages/Storage.php',
        'Luminova\\Storages\\StorageAdapters' => __DIR__ . '/../..' . '/src/Storages/StorageAdapters.php',
        'Luminova\\Storages\\Uploader' => __DIR__ . '/../..' . '/src/Storages/Uploader.php',
        'Luminova\\Template\\Helper' => __DIR__ . '/../..' . '/src/Template/Helper.php',
        'Luminova\\Template\\Layout' => __DIR__ . '/../..' . '/src/Template/Layout.php',
        'Luminova\\Template\\Smarty' => __DIR__ . '/../..' . '/src/Template/Smarty.php',
        'Luminova\\Template\\TemplateTrait' => __DIR__ . '/../..' . '/src/Template/TemplateTrait.php',
        'Luminova\\Template\\Twig' => __DIR__ . '/../..' . '/src/Template/Twig.php',
        'Luminova\\Template\\ViewResponse' => __DIR__ . '/../..' . '/src/Template/ViewResponse.php',
        'Luminova\\Time\\Task' => __DIR__ . '/../..' . '/src/Time/Task.php',
        'Luminova\\Time\\Time' => __DIR__ . '/../..' . '/src/Time/Time.php',
        'Luminova\\Time\\Timestamp' => __DIR__ . '/../..' . '/src/Time/Timestamp.php',
        'Luminova\\Utils\\Queue' => __DIR__ . '/../..' . '/src/Utils/Queue.php',
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
