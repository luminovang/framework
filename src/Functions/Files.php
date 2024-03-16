<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Functions;

use \Luminova\Application\Paths;

class Files
{
    /**
	 * Copy files and folders from the source directory to the destination directory.
	 *
	 * @param string $origin The source directory.
	 * @param string $dest The destination directory.
	 *
	 * @return bool True if the copy operation is successful, false otherwise.
	 */
	public static function copy(string $origin, string $dest): bool
	{
		Paths::createDirectory($dest);

		$dir = opendir($origin);

		if (!$dir) {
			return false;
		}

		while (false !== ($file = readdir($dir))) {
			if (($file != '.') && ($file != '..')) {
				$srcFile = $origin . DIRECTORY_SEPARATOR . $file;
				$destFile = $dest . DIRECTORY_SEPARATOR . $file;

				if (is_dir($srcFile)) {
					static::copy($srcFile, $destFile);
				} else {
					copy($srcFile, $destFile);
				}
			}
		}
		closedir($dir);
		return true;
	}

	/**
	 * Download a file to the user's browser.
	 *
	 * @param string $file The full file path to download.
	 * @param string $name The filename as it will be shown in the download.
	 * @param bool $delete Whether to delete the file after download (default: false).
     * 
     * @return bool
	 */
	public static function download(string $file, ?string $name = null, bool $delete = false): bool
	{
		if (file_exists($file)) {
			$filename = $name ?? basename($file);
			header('Content-Type: ' . (mime_content_type($file) ?? 'application/octet-stream'));
			header('Content-Disposition: attachment; filename="' . $filename . '"');
			header('Content-Transfer-Encoding: binary');
			header('Content-Length: ' . filesize($file));
			header('Cache-Control: no-store, no-cache, must-revalidate');
			header('Cache-Control: post-check=0, pre-check=0', false);
			header('Pragma: no-cache');
			$read = readfile($file);

            if($read !== false){
                if ($delete) {
                    unlink($file);
                }

                return true;
            }
		}

        return false;
	}

    /**
	 * Deletes files and folders.
	 *
	 * @param string $dir   Directory to delete files.
	 * @param bool   $base  Remove the base directory once done (default is false).
     * 
	 * @return bool         Returns true once the function is called.
	 */
	public static function remove(string $dir, bool $base = false): bool 
	{
		if (!file_exists($dir)) {
			return false;
		}
		
		if(is_dir($dir)){
			if (substr($dir, -1) !== DIRECTORY_SEPARATOR) {
				$dir .= DIRECTORY_SEPARATOR;
			}

			$files = glob($dir . '*', GLOB_MARK);
		}else{
			$files = glob($dir . '*');
		}

		foreach ($files as $file) {
			if (is_dir($file)) {
				static::remove($file, true);
			} else {
				unlink($file);
			}
		}

		if ($base) {
			rmdir($dir);
		}

		return true;
	}
}