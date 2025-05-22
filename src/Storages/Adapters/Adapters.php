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
     * Local filesystem storage.
     * Used for saving files on the local server.
     */
    public const LOCAL = 'local';

    /**
     * FTP-based storage.
     * Used for file transfers via standard FTP protocol.
     */
    public const FTP = 'ftp';

    /**
     * In-memory storage.
     * Temporary storage ideal for fast read/write operations, non-persistent.
     */
    public const MEMORY = 'memory';

    /**
     * Amazon S3 storage (synchronous).
     * Uses AWS S3 SDK to store and retrieve files in S3 buckets.
     */
    public const AWS_S3 = 'aws-s3';

    /**
     * Amazon S3 storage (asynchronous).
     * For background uploads to S3, allowing non-blocking storage operations.
     */
    public const AWS_ASYNC_S3 = 'aws-async-s3';

    /**
     * Azure Blob Storage.
     * Microsoft's cloud-based object storage solution for unstructured data.
     */
    public const AZURE_BLOB = 'azure-blob';

    /**
     * Google Cloud Storage.
     * Google’s scalable object storage service for various content types.
     */
    public const GOOGLE_CLOUD = 'google-cloud';

    /**
     * SFTP v3-based storage.
     * Secure file transfer using the SFTP (SSH File Transfer Protocol) version 3.
     */
    public const SFTP_V3 = 'sftp-v3';

    /**
     * WebDAV-based storage.
     * File management using the Web Distributed Authoring and Versioning protocol.
     */
    public const WEB_DEV = 'web-dev';

    /**
     * ZIP archive storage.
     * Stores files inside a compressed ZIP archive for packaging or export.
     */
    public const ZIP_ARCHIVE = 'zip-archive';

    /** 
     * Storage client instance.
     * 
     * @var object<\T>|null $client
     */
    private static ?object $client = null;

    /**
     * Available storage adapters.
     * 
     * @var array $libraries
     */
    protected static array $libraries = [
        self::LOCAL => [LocalAdapter::class],
        self::FTP => [FtpAdapter::class, FtpConnectionOptions::class],
        self::MEMORY => [MemoryAdapter::class, ReadOnlyAdapter::class],
        self::AWS_S3 => [AwsS3V3Adapter::class, S3Client::class],
        self::AWS_ASYNC_S3 => [AsyncAwsS3Adapter::class, S3AsyncClient::class],
        self::AZURE_BLOB => [AzureBlobAdapter::class, BlobRestProxy::class],
        self::GOOGLE_CLOUD => [GoogleCloudAdapter::class, GoggleClient::class],
        //'sftp-v2' => [SftpV2Adapter::class, SftpV2Client::class],
        self::SFTP_V3 => [SftpV3Adapter::class, SftpV3Client::class],
        self::WEB_DEV => [WebDAVAdapter::class, WebDevClient::class],
        self::ZIP_ARCHIVE => [ZipAdapter::class, ZipClient::class]
    ];
    
    /**
     * Get the storage client instance.
     * 
     * @return object<\T> The client instance.
     */
    public function getClient(): ?object
    {
        return self::$client;
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
            case self::LOCAL:
                $visibility = isset($config['visibility']) ? UnixVisibility::fromArray($config['visibility']): null;
                $disallow = $config['disallow_links'] ? LocalAdapter::DISALLOW_LINKS : LocalAdapter::SKIP_LINKS;
                return new LocalAdapter(
                    $basePath, 
                    $visibility, 
                    $config['lock_flags'] ?? LOCK_EX, 
                    $disallow
                );
            case self::FTP:
                return new FtpAdapter(FtpConnectionOptions::fromArray($config));
            case self::MEMORY:
                $adapter = new MemoryAdapter();
                return $config['readonly'] ? new ReadOnlyAdapter($adapter) : $adapter;
            case self::AWS_S3:
                self::$client = new S3Client($config['configuration']);
                return new AwsS3V3Adapter(
                    self::$client, 
                    $config['bucket'] ?? '', 
                    $basePath, 
                    new AwsVisibility($config['visibility'] ?? 'public')
                );
            case self::AWS_ASYNC_S3:
                self::$client = new S3AsyncClient($config['configuration']);
                return new AsyncAwsS3Adapter(
                    self::$client, 
                    $config['bucket'] ?? '', 
                    $basePath, 
                    new AwsVisibility($config['visibility'] ?? 'public')
                );
            case self::AZURE_BLOB:
                self::$client = BlobRestProxy::createBlobService($config['dns'] ?? '');
                return new AzureBlobAdapter(self::$client, $config['container'] ?? '', $basePath);
            case 'google-cloud':
                if ($authCache = ($config['configuration']['authCache'] ?? false)) {
                    if (is_string($authCache)) {
                        $config['configuration']['authCache'] = new $authCache('google_file_auth', 'credentials/authCache');
                    }
                }                

                self::$client = new GoggleClient($config['configuration']);
                return new GoogleCloudAdapter(self::$client->bucket($config['bucket']), $basePath);
            case self::WEB_DEV:
                self::$client = new Client([
                    'baseUri' => $config['baseurl'],
                    'userName' => $config['username'],
                    'password' => $config['password']
                ]);
                return new WebDAVAdapter(self::$client);
            case self::SFTP_V3:
                $visibility = isset($config['visibility']) ? UnixVisibility::fromArray($config['visibility']): null;
                return new SftpV3Adapter(self::newSftpProvider(3, $config), $config['root'], $visibility); 
            /*case 'sftp-v2':
                $visibility = isset($config['visibility']) ? UnixVisibility::fromArray($config['visibility']): null;
                return new SftpV2Adapter(self::newSftpProvider(2, $config), $config['root'], $visibility); */
            case self::ZIP_ARCHIVE:
                self::$client = new ZipClient($config['path']);
                return new ZipAdapter(self::$client);
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
        self::$client = new $sftpClass(
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

        return self::$client;
    }
}