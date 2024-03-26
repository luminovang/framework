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
		make_dir($dest);

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
		if (file_exists($file) && is_readable($file)) {
			$filename = $name ?? basename($file);
			$mime = mime_content_type($file) ?? 'application/octet-stream';

			// Prevent caching
			header('Content-Type: ' . $mime);
			header('Content-Disposition: attachment; filename="' . $filename . '"');
			header('Content-Transfer-Encoding: binary');
			header('Expires: 0');
			header('Cache-Control: must-revalidate');
			header('Pragma: public');
			header('Content-Length: ' . filesize($file));

			$read = readfile($file);

			if ($delete && $read !== false) {
				unlink($file);
			}

			return $read !== false;
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