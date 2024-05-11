<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Storages\Adapters;

use \Luminova\Exceptions\StorageException;
use \League\Flysystem\FilesystemAdapter;
use \League\Flysystem\Local\LocalFilesystemAdapter as LocalAdapter;
use \League\Flysystem\UnixVisibility\PortableVisibilityConverter as UnixVisibility;

use \League\Flysystem\Ftp\FtpAdapter;
use \League\Flysystem\Ftp\FtpConnectionOptions;
use \League\Flysystem\Ftp\FtpConnectionProvider;
use \League\Flysystem\Ftp\NoopCommandConnectivityChecker;

use \League\Flysystem\InMemory\InMemoryFilesystemAdapter as MemoryAdapter;
use \League\Flysystem\ReadOnly\ReadOnlyFilesystemAdapter as ReadOnlyAdapter;

use \Aws\S3\S3Client;
use \AsyncAws\S3\S3Client as S3AsyncClient;
use \League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use \League\Flysystem\AsyncAwsS3\AsyncAwsS3Adapter;
use \League\Flysystem\AwsS3V3\PortableVisibilityConverter as AwsVisibility;

use \League\Flysystem\AzureBlobStorage\AzureBlobStorageAdapter as AzureBlobAdapter;
use \MicrosoftAzure\Storage\Blob\BlobRestProxy;

use \League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter as GoogleCloudAdapter;
use \Google\Cloud\Storage\StorageClient as GoggleClient;

use \League\Flysystem\PhpseclibV2\SftpConnectionProvider as SftpV2Client;
use \League\Flysystem\PhpseclibV2\SftpAdapter as SftpV2Adapter;
use \League\Flysystem\PhpseclibV3\SftpConnectionProvider as SftpV3Client;
use \League\Flysystem\PhpseclibV3\SftpAdapter as SftpV3Adapter;

use \League\Flysystem\WebDAV\WebDAVAdapter;
use \Sabre\DAV\Client as WebDevClient;
use \League\Flysystem\ZipArchive\ZipArchiveAdapter as ZipAdapter;
use \League\Flysystem\ZipArchive\FilesystemZipArchiveProvider as ZipClient;

class Adapters
{
    /** 
     * @var mixed|null $client Client instance 
    */
    private static mixed $client = null;

    /**
     * Available storage adapters.
     * 
     * @var array $libraries
     */
    protected static array $libraries = [
        'local' => [LocalAdapter::class],
        'ftp' => [FtpAdapter::class, FtpConnectionOptions::class],
        'memory' => [MemoryAdapter::class, ReadOnlyAdapter::class],
        'aws-s3' => [AwsS3V3Adapter::class, S3Client::class],
        'aws-async-s3' => [AsyncAwsS3Adapter::class, S3AsyncClient::class],
        'azure-blob' => [AzureBlobAdapter::class, BlobRestProxy::class],
        'google-cloud' => [GoogleCloudAdapter::class, GoggleClient::class],
        //'sftp-v2' => [SftpV2Adapter::class, SftpV2Client::class],
        'sftp-v3' => [SftpV3Adapter::class, SftpV3Client::class],
        'web-dev' => [WebDAVAdapter::class, WebDevClient::class],
        'zip-archive' => [ZipAdapter::class, ZipClient::class]
    ];
    
    /**
     * Get the storage client instance.
     * 
     * @return mixed The client instance.
     */
    public function getClient(): mixed
    {
        return static::$client;
    }

    /**
     * Get the appropriate filesystem adapter based on the current adapter.
     * 
     * @param string $adapter The adapter name.
     * @param array $config The adapter configuration.
     * 
     * @return FilesystemAdapter The filesystem adapter.
     */
    protected static function getAdapter(string $adapter, array $config): FilesystemAdapter
    {
        $basePath = $config['base'] ?? '';

        switch ($adapter) {
            case 'local':
                $visibility = isset($config['visibility']) ? UnixVisibility::fromArray($config['visibility']): null;
                $disallow = $config['disallow_links'] ? LocalAdapter::DISALLOW_LINKS : LocalAdapter::SKIP_LINKS;
                return new LocalAdapter(
                    $basePath, 
                    $visibility, 
                    $config['lock_flags'] ?? LOCK_EX, 
                    $disallow
                );
            case 'ftp':
                return new FtpAdapter(FtpConnectionOptions::fromArray($config));
            case 'memory':
                $adapter = new MemoryAdapter();
                return $config['readonly'] ? new ReadOnlyAdapter($adapter) : $adapter;
            case 'aws-s3':
                static::$client = new S3Client($config['configuration']);
                return new AwsS3V3Adapter(
                    static::$client, 
                    $config['bucket'] ?? '', 
                    $basePath, 
                    new AwsVisibility($config['visibility'] ?? 'public')
                );
            case 'aws-async-s3':
                static::$client = new S3AsyncClient($config['configuration']);
                return new AsyncAwsS3Adapter(
                    static::$client, 
                    $config['bucket'] ?? '', 
                    $basePath, 
                    new AwsVisibility($config['visibility'] ?? 'public')
                );
            case 'azure-blob':
                static::$client = BlobRestProxy::createBlobService($config['dns'] ?? '');
                return new AzureBlobAdapter(static::$client, $config['container'] ?? '', $basePath);
            case 'google-cloud':
                if ($authCache = ($config['configuration']['authCache'] ?? false)) {
                    if (is_string($authCache)) {
                        $config['configuration']['authCache'] = new $authCache('google_file_auth', 'credentials/authCache');
                    }
                }                

                static::$client = new GoggleClient($config['configuration']);
                return new GoogleCloudAdapter(static::$client->bucket($config['bucket']), $basePath);
            case 'web-dev':
                static::$client = new Client([
                    'baseUri' => $config['baseurl'],
                    'userName' => $config['username'],
                    'password' => $config['password']
                ]);
                return new WebDAVAdapter(static::$client);
            case 'sftp-v3':
                $visibility = isset($config['visibility']) ? UnixVisibility::fromArray($config['visibility']): null;
                return new SftpV3Adapter(static::newSftpProvider(3, $config), $config['root'], $visibility); 
            /*case 'sftp-v2':
                $visibility = isset($config['visibility']) ? UnixVisibility::fromArray($config['visibility']): null;
                return new SftpV2Adapter(static::newSftpProvider(2, $config), $config['root'], $visibility); */
            case 'zip-archive':
                static::$client = new ZipClient($config['path']);
                return new ZipAdapter(static::$client);
            default:
                return null;
        }
    }

    /**
     * Check if required classes are installed for a given adapter.
     * 
     * @param string $adapter The adapter name.
     * @return void
     * @throws StorageException If required class is not found.
     */
    protected static function isInstalled(string $adapter): void 
    {
        $classes = static::$libraries[$adapter] ?? null;
        if ($classes === null) {
            throw new StorageException('Invalid adapter context "' . $adapter . '"');
        }
    
        foreach ($classes as $class) {
            if (!class_exists($class)) {
                throw new StorageException('Class not found "' . $class . '", install required dependency first.' );
            }
        }
    } 

    /**
     * Create and return the sftp client instance 
     * 
     * @param int $version
     * @param array $config
     * 
     * @return SftpV3Client|SftpV2Client new class instance.
    */
    private static function newSftpProvider(int $version, array $config): SftpV3Client|SftpV2Client
    {
        $sftpClass = ($version === 3) ? SftpV3Client::class : SftpV2Client::class;
        static::$client = new $sftpClass(
            $config['host'],
            $config['username'], 
            $config['password'] ?? null,
            $config['private_key_path'],
            $config['passphrase'],
            $config['port'] ?? 22,
            $config['agent'] ?? false,
            $config['timeout'] ?? 10,
            $config['retry'] ?? 4,
            $config['fingerprint'] ?? null,
            null
        );

        return static::$client;
    }
}