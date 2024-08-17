<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Command\Auth;

class Handler
{
    private string $savePath = '';

    public function __construct(string $path = '/writeable/tmp/')
    {
        $this->savePath = root($path);

        if (!is_dir($this->savePath)) {
            make_dir($this->savePath, 0777, true);
        }
    }

    public function getSavePath(?string $idFile = null): string 
    {
        return $this->savePath . (($idFile === null) ? '' : ltrim($idFile, TRIM_DS));
    }

    public function read(string $token)
    {
        $file = "{$this->savePath}sess_{$token}";
        if (file_exists($file)) {
            return get_content($file);
        }

        return '';
    }


    public function write($token, $data)
    {
        $file = "{$this->savePath}sess_{$token}";
        return write_content($file, $data) !== false;
    }

    public function destroy($token)
    {
        $file = "{$this->savePath}sess_{$token}";
        if (file_exists($file)) {
            unlink($file);
        }
    }

    public function gc(int $maxLifetime): void
    {
        foreach (glob("{$this->savePath}sess_*") as $file) {
            if (filemtime($file) + $maxLifetime < time()) {
                unlink($file);
            }
        }
    }
}