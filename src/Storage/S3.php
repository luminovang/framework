<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Storage;

use \Aws\S3\S3Client;
use \Aws\Exception\AwsException;
use \Aws\S3\Exception\S3Exception;
use \Aws\S3\PostObjectV4;

final class S3
{
    private static ?S3Client $client = null;

    public function __construct(string|array $key_or_config, string $secret = '', string $region = '')
    {
        if(is_array($key_or_config)){
            $config = $key_or_config;
        }else{
            $config = [
                'credentials' => [
                    'key'    => $key_or_config,
                    'secret' => $secret,
                ],
                'region' => $region,
                'version' => 'latest',
            ];
        }

        static::$client ??= new S3Client($config);
    }
    

    public function upload(string $bucket, string $filepath, string $key): bool
    {
        try {
            $result = static::$client->putObject([
                'Bucket' => $bucket,
                'Key'    => $key,
                'Body'   => fopen($filepath, 'r'),
            ]);

            return true;
        } catch (AwsException $e) {
            logger('error', $e->getMessage());
            return false;
        }
    }

    public static function folderSize(string $bucket, string $folder): int 
    {
		$size = 0;
		try {
			$objects = static::$client->getIterator('ListObjects', [
				"Bucket" => $bucket,
				"Prefix" => $folder
			]);
			foreach ($objects as $object) {
				$size += $object['Size'];
			}
		} catch (S3Exception $e) {
			$size = 0;
		}
		return $size;
	}

    public static function fileSize(string $bucket, string $key): int 
    {
		$size = 0;
		try {
			$objects = static::$client->headObject([
				"Bucket" => $bucket,
				"Key" => $key
			]);
			$size = $objects['ContentLength'];
		} catch (S3Exception $e) {
			$size = 0;
		}

		return $size;
	}

    public static function fileExist(string $bucket, string $key): bool 
    {
		$state = false;
		try{
			$state = static::$client->doesObjectExist($bucket,  $key);
		} catch (S3Exception $e) {
			$state = false;
		}
		return $state;
	}

    public static function bucket(string $filetype = 'jpg', string $folder = ''): string 
    {
        $id = uniqid('bucket');
        $hash = md5($id . $filetype . $folder);
        if($folder === ''){
            $destination = $hash . '.' . $filetype;
        }else{
            $destination = trim($folder, '/') . '/' . $hash . '.' . $filetype;
        }

		return $destination;
	}

    public static function put(string $bucket, string $key, string $file): bool
    {
		$state = false;
		try{
			$result = static::$client->putObject([
                'Bucket'=> $bucket,
                'Key' =>  $key,
                'SourceFile' => $file,
                'StorageClass' => 'REDUCED_REDUNDANCY'
			]);
		} catch (S3Exception $e) {
			$state = false;
		}
		return $state;
	}

    public static function bucketUrl(string $bucket, string $region): string
    {
		return "https://{$bucket}.s3.{$region}.amazonaws.com/";
	}

	public static function signature(string $bucket, string $type, string $iam): string 
    {
		$signature = base64_encode(hash_hmac('sha1', static::policy($bucket, $type), $iam, true));

        return $signature;
	}

    public static function policy(string $bucket, string $type, string $expires = '+1 day'): string
    {
		$policy = base64_encode(json_encode([
			'expiration' => date('Y-m-d\TH:i:s.000\Z', strtotime($expires)),  
			'conditions' => [
				['bucket' => $bucket],
				['acl' => 'public-read'],
				['starts-with', '$key', ''],
				['starts-with', '$Content-Type', $type],
				['starts-with', '$name', ''],
				['starts-with', '$Filename', ''], 
            ]
		]));

		return $policy;
	}

    public static function presignedRequest(string $bucket, string $key, string $expires = '+1 week'): string 
    {
		try{
			$cmd = static::$client->getCommand('GetObject', [
				'Bucket' => $bucket,
				'Key' => $key
			]);
	
			$request = static::$client->createPresignedRequest($cmd, $expires);

			return (string) $request->getUri();
		} catch (S3Exception $e) {
			return '';
		}
		return '';
	}

    public function post(string $bucket, string $expires = '+1 week', string $startWith = ''){
		$url = null;
		try{
			$postObject = new PostObjectV4(
				static::$client,
                $bucket,
				[
					'acl' => 'public-read'
                ],
				[
					['acl' => 'public-read'],
					['bucket' => $bucket],
					['starts-with', '$key', $startWith]
                ],
				$expires
			);
			$attribute = $postObject->getFormAttributes();
			$inputs = $postObject->getFormInputs();

			return (object) [
				"attributes" => $attribute,
				"inputs" => $inputs
            ];
		} catch (S3Exception $e) {
			$url = null;
		}
		return $url;
	}
}
