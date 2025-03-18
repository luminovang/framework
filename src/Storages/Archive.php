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
namespace Luminova\Storages;

use \ZipArchive;
use \PharData;
use \Phar;
use \Luminova\Exceptions\FileException;
use \Exception;

class Archive 
{
    /**
     * Instance of PHP ZipArchive.
     * 
     * @var ZipArchive|null $zip 
     */
    private static ?ZipArchive $zip = null;

    /**
     * Creates a zip archive from a file or directory.
     *
     * If the $source is a file, it adds it as a single item to the archive.
     * If the $source is a directory, it adds all files within it.
     *
     * @param string $destination The path where the zip file will be created.
     * @param string $source The file or directory to be zipped.
     * @param array<int,string> $ignores Optional array list for files and directories to ignore while creating archives
     *                          (e.g, ['path/foo', '/bar/', 'foo.xml', '.bar']).
     * @param int  &$added A reference variable to count added files.
     * @param int &$skipped A reference variable to count skipped files.
     *
     * @return bool Return true if one or more files are added to the archive; otherwise, false.
     * @throws FileException If an error occurs during the zip process.
     */
    public static function zip(
        string $destination, 
        string $source, 
        array $ignores = [],
        int &$added = 0,
        int &$skipped = 0,
    ): bool
    {
        if (!file_exists($source)) {
            return false; 
        }

        try {
            self::ensureDirectory(dirname($destination));
            self::createZipObject();

            if (self::$zip->open($destination, ZipArchive::CREATE|ZipArchive::OVERWRITE) !== true) {
                return false;
            }

            [$added, $skipped] = is_file($source) 
                ? self::addItem(
                    $source, 
                    '', 
                    $ignores
                )
                : self::addItems(
                    $source, 
                    '', 
                    $ignores
                );
            
            self::close(); 
            
            return $added > 0; 
        } catch (Exception $e) {
            throw new FileException($e->getMessage(), $e->getCode(), $e);
        }

        self::close(); 
        return false;
    }

    /**
     * Creates a .tar.gz archive from the specified origin (file or directory).
     *
     * @param string $destination The path where the .tar.gz file will be saved (without extension).
     * @param string $source The file or directory to be archived.
     * 
     * @return bool Returns true on success or false on failure.
     */
    public static function tarGz(string $destination, string $source): bool
    {
        if (!file_exists($source)) {
            return false; 
        }

        $destination = preg_replace('/(\.tar\.gz|\.tar)$/', '', $destination) . '.tar';
        $gzFile = $destination . '.gz';
        
        self::ensureDirectory(dirname($destination));

        self::create($destination, $source);
        return self::compress($destination, $gzFile);
    }


    /**
     * Creates a .tar file from the specified origin (file or directory).
     *
     * @param string $tarFile The path where the .tar file will be saved.
     * @param string $source The file or directory to be archived.
     * 
     * @return void
     */
    private static function create(string $tarFile, string $source, string $pattern = ''): void
    {
        try{
            $phar = new PharData($tarFile);

            if (is_file($source)) {
                $phar->addFile($source, basename($source)); 
            } else {
                $phar->buildFromDirectory($source, $pattern);
            }
            $phar->compress(Phar::GZ);
        }catch(Exception $e){
            throw new FileException(
                $e->getMessage(), 
                ($e->getCode() === 0) ? FileException::FILESYSTEM_ERROR : $e->getCode(), 
                $e
            );
        }
    }

    /**
     * Compresses a .tar file into a .tar.gz file.
     *
     * @param string $tarFile The path of the .tar file to compress.
     * @param string $gzFile The path where the compressed .tar.gz file will be saved.
     * 
     * @return bool Returns true on success or false on failure.
     */
    private static function compress(string $tarFile, string $gzFile): bool
    {
        if (!file_exists($tarFile)) {
            return false;
        }

        return rename($tarFile, $gzFile);
    }

    /**
     * Ensure the directory exists; create if it doesn't.
     *
     * @param string $path The directory path to ensure.
     * @return void
     */
    private static function ensureDirectory(string $path): void 
    {
        if (!is_dir($path)) {
            make_dir($path);
        }
    }

    /**
     * Initialize the ZipArchive object.
     *
     * @return void
     */
    private static function createZipObject(): void 
    {
        if (!(self::$zip instanceof ZipArchive)) {
            self::$zip = new ZipArchive();
        }
    }

    /**
     * Add a single file to the zip archive.
     *
     * @param string $filepath The filepath to zip.
     * @param string $zip_folder The folder path inside the zip.
     * @param array $ignores Paths to ignore.
     * 
     * @return array{0:int,1:int} Returns the count of added and skipped items.
     */
    private static function addItem(
        string $filepath, 
        string $zip_folder,
        array $ignores
    ): array
    {
        $relative_path = $zip_folder 
            ? $zip_folder 
            : $zip_folder . DIRECTORY_SEPARATOR . basename($filepath);

        if (!self::ignore($ignores, $relative_path)) {
            self::$zip->addFile($filepath, $relative_path);
            return [1, 0];
        }

        return [0, 1];
    }

    /**
     * Recursively add files and directories to the zip archive.
     *
     * @param string $folder The folder to zip.
     * @param string $zip_folder The folder path inside the zip.
     * @param array $ignores Paths to ignore.
     * 
     * @return array{0:int,1:int} Returns the count of added and skipped items.
     */
    private static function addItems(
        string $folder, 
        string $zip_folder,
        array $ignores
    ): array
    {
        $added = 0;
        $skipped = 0;
        $files = scandir($folder);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filepath = $folder . DIRECTORY_SEPARATOR . $file;
            $relative_path = $zip_folder . DIRECTORY_SEPARATOR . $file;

            if (is_dir($filepath)) {
                if (!self::ignore($ignores, $relative_path)) {
                    self::$zip->addEmptyDir($relative_path);
                    [$newAdded, $newSkipped] = self::addItems(
                        $filepath, 
                        $relative_path, 
                        $ignores
                    );
                    $added += $newAdded;
                    $skipped += $newSkipped;
                } else {
                    $skipped++;
                }
            } else {
                [$newAdded, $newSkipped] = self::addItem(
                    $filepath, 
                    $relative_path, 
                    $ignores
                );
                $added += $newAdded;
                $skipped += $newSkipped;
            }
        }

        return [$added, $skipped];
    }

    /**
     * Check if a path or filename should be ignored based on ignore patterns.
     *
     * @param string[] $ignores The patterns to ignore.
     * @param string $file The file path to check.
     * 
     * @return bool Returns true if the path matches any ignore pattern.
     */
    private static function ignore(array $ignores, string $file): bool
    {
        if ($ignores === []) {
            return false;
        }

        $fileName = basename($file);
        foreach ($ignores as $ignore) {
            if (
                fnmatch($ignore, self::normalize($file)) || 
                fnmatch(strtolower($ignore), strtolower(self::normalize($fileName)))
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Normalize the path by trimming leading dots.
     *
     * @param string $path The path to normalize.
     * 
     * @return string Return the normalized path.
     */
    private static function normalize(string $path): string
    {
        return ltrim($path, '.');
    }

    /**
     * Close the active archive (opened or newly created)
     * 
     * @return void
     */
    private static function close(): void 
    {
        self::$zip->close();
        self::$zip = null; 
    }
}