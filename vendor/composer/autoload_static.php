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
        'Luminova\\Application\\Application' => __DIR__ . '/../..' . '/src/Application/Application.php',
        'Luminova\\Application\\Paths' => __DIR__ . '/../..' . '/src/Application/Paths.php',
        'Luminova\\Application\\Services' => __DIR__ . '/../..' . '/src/Application/Services.php',
        'Luminova\\Arrays\\ArrayCountable' => __DIR__ . '/../..' . '/src/Arrays/ArrayCountable.php',
        'Luminova\\Arrays\\ArrayInput' => __DIR__ . '/../..' . '/src/Arrays/ArrayInput.php',
        'Luminova\\Arrays\\Arrays' => __DIR__ . '/../..' . '/src/Arrays/Arrays.php',
        'Luminova\\Base\\BaseApplication' => __DIR__ . '/../..' . '/src/Base/BaseApplication.php',
        'Luminova\\Base\\BaseCommand' => __DIR__ . '/../..' . '/src/Base/BaseCommand.php',
        'Luminova\\Base\\BaseConfig' => __DIR__ . '/../..' . '/src/Base/BaseConfig.php',
        'Luminova\\Base\\BaseController' => __DIR__ . '/../..' . '/src/Base/BaseController.php',
        'Luminova\\Base\\BaseFunction' => __DIR__ . '/../..' . '/src/Base/BaseFunction.php',
        'Luminova\\Base\\BaseModel' => __DIR__ . '/../..' . '/src/Base/BaseModel.php',
        'Luminova\\Base\\BasePaths' => __DIR__ . '/../..' . '/src/Base/BasePaths.php',
        'Luminova\\Base\\BaseViewController' => __DIR__ . '/../..' . '/src/Base/BaseViewController.php',
        'Luminova\\Cache\\Cache' => __DIR__ . '/../..' . '/src/Cache/Cache.php',
        'Luminova\\Cache\\Compress' => __DIR__ . '/../..' . '/src/Cache/Compress.php',
        'Luminova\\Cache\\FileCache' => __DIR__ . '/../..' . '/src/Cache/FileCache.php',
        'Luminova\\Cache\\MemoryCache' => __DIR__ . '/../..' . '/src/Cache/MemoryCache.php',
        'Luminova\\Cache\\Optimizer' => __DIR__ . '/../..' . '/src/Cache/Optimizer.php',
        'Luminova\\Command\\Colors' => __DIR__ . '/../..' . '/src/Command/Colors.php',
        'Luminova\\Command\\Commands' => __DIR__ . '/../..' . '/src/Command/Commands.php',
        'Luminova\\Command\\Console' => __DIR__ . '/../..' . '/src/Command/Console.php',
        'Luminova\\Command\\Novakit\\AvailableCommands' => __DIR__ . '/../..' . '/src/Command/Novakit/AvailableCommands.php',
        'Luminova\\Command\\Novakit\\Database' => __DIR__ . '/../..' . '/src/Command/Novakit/Database.php',
        'Luminova\\Command\\Novakit\\Generators' => __DIR__ . '/../..' . '/src/Command/Novakit/Generators.php',
        'Luminova\\Command\\Novakit\\Help' => __DIR__ . '/../..' . '/src/Command/Novakit/Help.php',
        'Luminova\\Command\\Novakit\\Lists' => __DIR__ . '/../..' . '/src/Command/Novakit/Lists.php',
        'Luminova\\Command\\Novakit\\Server' => __DIR__ . '/../..' . '/src/Command/Novakit/Server.php',
        'Luminova\\Command\\Terminal' => __DIR__ . '/../..' . '/src/Command/Terminal.php',
        'Luminova\\Command\\TerminalGenerator' => __DIR__ . '/../..' . '/src/Command/TerminalGenerator.php',
        'Luminova\\Command\\TextUtils' => __DIR__ . '/../..' . '/src/Command/TextUtils.php',
        'Luminova\\Composer\\BaseComposer' => __DIR__ . '/../..' . '/src/Composer/BaseComposer.php',
        'Luminova\\Composer\\Builder' => __DIR__ . '/../..' . '/src/Composer/Builder.php',
        'Luminova\\Composer\\Updater' => __DIR__ . '/../..' . '/src/Composer/Updater.php',
        'Luminova\\Config\\Configuration' => __DIR__ . '/../..' . '/src/Config/Configuration.php',
        'Luminova\\Config\\Database' => __DIR__ . '/../..' . '/src/Config/Database.php',
        'Luminova\\Config\\DotEnv' => __DIR__ . '/../..' . '/src/Config/DotEnv.php',
        'Luminova\\Controllers\\Controller' => __DIR__ . '/../..' . '/src/Controllers/Controller.php',
        'Luminova\\Controllers\\ViewController' => __DIR__ . '/../..' . '/src/Controllers/ViewController.php',
        'Luminova\\Cookies\\Cookie' => __DIR__ . '/../..' . '/src/Cookies/Cookie.php',
        'Luminova\\Cookies\\CookieInterface' => __DIR__ . '/../..' . '/src/Cookies/CookieInterface.php',
        'Luminova\\Cookies\\Exception\\CookieException' => __DIR__ . '/../..' . '/src/Cookies/Exception/CookieException.php',
        'Luminova\\Database\\Columns' => __DIR__ . '/../..' . '/src/Database/Columns.php',
        'Luminova\\Database\\Connection' => __DIR__ . '/../..' . '/src/Database/Connection.php',
        'Luminova\\Database\\Drivers\\DriversInterface' => __DIR__ . '/../..' . '/src/Database/Drivers/DriversInterface.php',
        'Luminova\\Database\\Drivers\\MySqlDriver' => __DIR__ . '/../..' . '/src/Database/Drivers/MySqlDriver.php',
        'Luminova\\Database\\Drivers\\PdoDriver' => __DIR__ . '/../..' . '/src/Database/Drivers/PdoDriver.php',
        'Luminova\\Database\\Query' => __DIR__ . '/../..' . '/src/Database/Query.php',
        'Luminova\\Database\\Results\\Statements' => __DIR__ . '/../..' . '/src/Database/Results/Statements.php',
        'Luminova\\Debugger\\PHPStanRules' => __DIR__ . '/../..' . '/src/Debugger/PHPStanRules.php',
        'Luminova\\Email\\Clients\\MailClientInterface' => __DIR__ . '/../..' . '/src/Email/Clients/MailClientInterface.php',
        'Luminova\\Email\\Clients\\NovaMailer' => __DIR__ . '/../..' . '/src/Email/Clients/NovaMailer.php',
        'Luminova\\Email\\Clients\\PHPMailer' => __DIR__ . '/../..' . '/src/Email/Clients/PHPMailer.php',
        'Luminova\\Email\\Clients\\SwiftMailer' => __DIR__ . '/../..' . '/src/Email/Clients/SwiftMailer.php',
        'Luminova\\Email\\Exceptions\\MailerException' => __DIR__ . '/../..' . '/src/Email/Exceptions/MailerException.php',
        'Luminova\\Email\\Helpers\\Helper' => __DIR__ . '/../..' . '/src/Email/Helpers/Helper.php',
        'Luminova\\Email\\Mailer' => __DIR__ . '/../..' . '/src/Email/Mailer.php',
        'Luminova\\Errors\\Codes' => __DIR__ . '/../..' . '/src/Errors/Codes.php',
        'Luminova\\Errors\\Error' => __DIR__ . '/../..' . '/src/Errors/Error.php',
        'Luminova\\Exceptions\\AppException' => __DIR__ . '/../..' . '/src/Exceptions/AppException.php',
        'Luminova\\Exceptions\\ClassException' => __DIR__ . '/../..' . '/src/Exceptions/ClassException.php',
        'Luminova\\Exceptions\\DatabaseException' => __DIR__ . '/../..' . '/src/Exceptions/DatabaseException.php',
        'Luminova\\Exceptions\\ErrorException' => __DIR__ . '/../..' . '/src/Exceptions/ErrorException.php',
        'Luminova\\Exceptions\\FileException' => __DIR__ . '/../..' . '/src/Exceptions/FileException.php',
        'Luminova\\Exceptions\\InvalidException' => __DIR__ . '/../..' . '/src/Exceptions/InvalidException.php',
        'Luminova\\Exceptions\\InvalidObjectException' => __DIR__ . '/../..' . '/src/Exceptions/InvalidObjectException.php',
        'Luminova\\Exceptions\\LuminovaException' => __DIR__ . '/../..' . '/src/Exceptions/LuminovaException.php',
        'Luminova\\Exceptions\\NotFoundException' => __DIR__ . '/../..' . '/src/Exceptions/NotFoundException.php',
        'Luminova\\Exceptions\\RuntimeException' => __DIR__ . '/../..' . '/src/Exceptions/RuntimeException.php',
        'Luminova\\Exceptions\\ValidationException' => __DIR__ . '/../..' . '/src/Exceptions/ValidationException.php',
        'Luminova\\Exceptions\\ViewNotFoundException' => __DIR__ . '/../..' . '/src/Exceptions/ViewNotFoundException.php',
        'Luminova\\Functions\\Document' => __DIR__ . '/../..' . '/src/Functions/Document.php',
        'Luminova\\Functions\\Escaper' => __DIR__ . '/../..' . '/src/Functions/Escaper.php',
        'Luminova\\Functions\\Files' => __DIR__ . '/../..' . '/src/Functions/Files.php',
        'Luminova\\Functions\\FunctionTrait' => __DIR__ . '/../..' . '/src/Functions/FunctionTrait.php',
        'Luminova\\Functions\\Functions' => __DIR__ . '/../..' . '/src/Functions/Functions.php',
        'Luminova\\Functions\\IPAddress' => __DIR__ . '/../..' . '/src/Functions/IPAddress.php',
        'Luminova\\Functions\\MathTrait' => __DIR__ . '/../..' . '/src/Functions/MathTrait.php',
        'Luminova\\Functions\\StringTrait' => __DIR__ . '/../..' . '/src/Functions/StringTrait.php',
        'Luminova\\Functions\\TorDetector' => __DIR__ . '/../..' . '/src/Functions/TorDetector.php',
        'Luminova\\Http\\AsyncClientInterface' => __DIR__ . '/../..' . '/src/Http/AsyncClientInterface.php',
        'Luminova\\Http\\Client\\Curl' => __DIR__ . '/../..' . '/src/Http/Client/Curl.php',
        'Luminova\\Http\\Client\\Guzzle' => __DIR__ . '/../..' . '/src/Http/Client/Guzzle.php',
        'Luminova\\Http\\CurlAsyncClient' => __DIR__ . '/../..' . '/src/Http/CurlAsyncClient.php',
        'Luminova\\Http\\Exceptions\\ClientException' => __DIR__ . '/../..' . '/src/Http/Exceptions/ClientException.php',
        'Luminova\\Http\\Exceptions\\ConnectException' => __DIR__ . '/../..' . '/src/Http/Exceptions/ConnectException.php',
        'Luminova\\Http\\Exceptions\\RequestException' => __DIR__ . '/../..' . '/src/Http/Exceptions/RequestException.php',
        'Luminova\\Http\\Exceptions\\ServerException' => __DIR__ . '/../..' . '/src/Http/Exceptions/ServerException.php',
        'Luminova\\Http\\GuzzleAsyncClient' => __DIR__ . '/../..' . '/src/Http/GuzzleAsyncClient.php',
        'Luminova\\Http\\Header' => __DIR__ . '/../..' . '/src/Http/Header.php',
        'Luminova\\Http\\Network' => __DIR__ . '/../..' . '/src/Http/Network.php',
        'Luminova\\Http\\NetworkAsync' => __DIR__ . '/../..' . '/src/Http/NetworkAsync.php',
        'Luminova\\Http\\NetworkClientInterface' => __DIR__ . '/../..' . '/src/Http/NetworkClientInterface.php',
        'Luminova\\Http\\NetworkRequest' => __DIR__ . '/../..' . '/src/Http/NetworkRequest.php',
        'Luminova\\Http\\NetworkResponse' => __DIR__ . '/../..' . '/src/Http/NetworkResponse.php',
        'Luminova\\Http\\Request' => __DIR__ . '/../..' . '/src/Http/Request.php',
        'Luminova\\Languages\\Translator' => __DIR__ . '/../..' . '/src/Languages/Translator.php',
        'Luminova\\Library\\Importer' => __DIR__ . '/../..' . '/src/Library/Importer.php',
        'Luminova\\Logger\\Logger' => __DIR__ . '/../..' . '/src/Logger/Logger.php',
        'Luminova\\Logger\\LoggerAware' => __DIR__ . '/../..' . '/src/Logger/LoggerAware.php',
        'Luminova\\Logger\\NovaLogger' => __DIR__ . '/../..' . '/src/Logger/NovaLogger.php',
        'Luminova\\Models\\Model' => __DIR__ . '/../..' . '/src/Models/Model.php',
        'Luminova\\Models\\PushMessage' => __DIR__ . '/../..' . '/src/Models/PushMessage.php',
        'Luminova\\Notifications\\FirebasePusher' => __DIR__ . '/../..' . '/src/Notifications/FirebasePusher.php',
        'Luminova\\Notifications\\FirebaseRealtime' => __DIR__ . '/../..' . '/src/Notifications/FirebaseRealtime.php',
        'Luminova\\Routing\\Bootstrap' => __DIR__ . '/../..' . '/src/Routing/Bootstrap.php',
        'Luminova\\Routing\\Router' => __DIR__ . '/../..' . '/src/Routing/Router.php',
        'Luminova\\Security\\Csrf' => __DIR__ . '/../..' . '/src/Security/Csrf.php',
        'Luminova\\Security\\Encryption\\AES' => __DIR__ . '/../..' . '/src/Security/Encryption/AES.php',
        'Luminova\\Security\\Encryption\\EncryptionInterface' => __DIR__ . '/../..' . '/src/Security/Encryption/EncryptionInterface.php',
        'Luminova\\Security\\InputValidator' => __DIR__ . '/../..' . '/src/Security/InputValidator.php',
        'Luminova\\Security\\ValidatorInterface' => __DIR__ . '/../..' . '/src/Security/ValidatorInterface.php',
        'Luminova\\Seo\\Meta' => __DIR__ . '/../..' . '/src/Seo/Meta.php',
        'Luminova\\Sessions\\CookieManager' => __DIR__ . '/../..' . '/src/Sessions/CookieManager.php',
        'Luminova\\Sessions\\Session' => __DIR__ . '/../..' . '/src/Sessions/Session.php',
        'Luminova\\Sessions\\SessionInterface' => __DIR__ . '/../..' . '/src/Sessions/SessionInterface.php',
        'Luminova\\Sessions\\SessionManager' => __DIR__ . '/../..' . '/src/Sessions/SessionManager.php',
        'Luminova\\Storage\\Helper' => __DIR__ . '/../..' . '/src/Storage/Helper.php',
        'Luminova\\Storage\\S3' => __DIR__ . '/../..' . '/src/Storage/S3.php',
        'Luminova\\Storage\\Uploader' => __DIR__ . '/../..' . '/src/Storage/Uploader.php',
        'Luminova\\Template\\Smarty' => __DIR__ . '/../..' . '/src/Template/Smarty.php',
        'Luminova\\Template\\Template' => __DIR__ . '/../..' . '/src/Template/Template.php',
        'Luminova\\Template\\TemplateTrait' => __DIR__ . '/../..' . '/src/Template/TemplateTrait.php',
        'Luminova\\Time\\Task' => __DIR__ . '/../..' . '/src/Time/Task.php',
        'Luminova\\Time\\Time' => __DIR__ . '/../..' . '/src/Time/Time.php',
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