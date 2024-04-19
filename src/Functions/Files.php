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
	 * @param string $file The full file path or content to to download.
	 * @param string $name The filename as it will be shown in the download.
	 * @param array $headers Optional passed headers for download.
	 * @param bool $delete Whether to delete the file after download (default: false).
	 * @param string|null $content to download
     * 
     * @return bool Return true on success, false on failure.
	 */
	public static function download(string $file, ?string $name = null, array $headers = [], bool $delete = false): bool
	{
		$isFile = false;

		if (file_exists($file) && is_readable($file)) {
			$isFile = true;
			$filename = $name ?? basename($file);
			$mime = mime_content_type($file) ?? 'application/octet-stream';
			$length = filesize($file);
		} else {
			$length = mb_strlen($file);
			$filename = $name ?? 'file_download.txt';
			$mime = 'application/octet-stream';
		}

		$extend = array_merge([
			'Content-Type' => $mime,
			'Content-Disposition' => 'attachment; filename="' . $filename,
			'ontent-Transfer-Encoding' => 'binary',
			'Expires' => 0,
			'Cache-Control' => 'must-revalidate',
			'Pragma' => 'public',
			'Content-Length' => $length,
		], $headers);

		foreach($extend as $key => $value) {
			header("{$key}: {$value}");
		}

		if ($isFile) {
			$read = readfile($file);

			if ($delete && $read !== false) {
				unlink($file);
			}

			return $read !== false;
		} else {
			echo $file;
			return true;
		}
	}

    /**
	 * Deletes files and folders.
	 *
	 * @param string $dir   Directory to delete files.
	 * @param bool   $delete_base  Remove the base directory once done (default is false).
     * 
	 * @return int Returns count of deleted files.
	 */
	public static function remove(string $dir, bool $delete_base = false): int 
	{
		$count = 0;

		if (!file_exists($dir)) {
			return $count;
		}
		
		$files = is_dir($dir) ? 
			glob(rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR. '*', GLOB_MARK) : 
			glob($dir . '*');
		//$files = glob(rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*', GLOB_MARK);

		foreach ($files as $file) {
			if (is_dir($file)) {
				static::remove($file, true);
			} else {
				unlink($file);
				$count++;
			}
		}

		if ($delete_base) {
			$count++;
			rmdir($dir);
		}

		return $count;
	}
}