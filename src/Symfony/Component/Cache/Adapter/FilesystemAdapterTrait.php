<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Adapter;

use Symfony\Component\Cache\Exception\InvalidArgumentException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
trait FilesystemAdapterTrait
{
    private $directory;
    private $tmp;

    private function init($namespace, $directory)
    {
        if (!isset($directory[0])) {
            $directory = sys_get_temp_dir().'/symfony-cache';
        }
        if (isset($namespace[0])) {
            if (preg_match('#[^-+_.A-Za-z0-9]#', $namespace, $match)) {
                throw new InvalidArgumentException(sprintf('Namespace contains "%s" but only characters in [-+_.A-Za-z0-9] are allowed.', $match[0]));
            }
            $directory .= '/'.$namespace;
        }
        if (!file_exists($dir = $directory.'/.')) {
            @mkdir($directory, 0777, true);
        }
        if (false === $dir = realpath($dir) ?: (file_exists($dir) ? $dir : false)) {
            throw new InvalidArgumentException(sprintf('Cache directory does not exist (%s)', $directory));
        }
        $dir .= DIRECTORY_SEPARATOR;
        // On Windows the whole path is limited to 258 chars
        if ('\\' === DIRECTORY_SEPARATOR && strlen($dir) > 234) {
            throw new InvalidArgumentException(sprintf('Cache directory too long (%s)', $directory));
        }

        $this->directory = $dir;
        $this->tmp = $this->directory.uniqid('', true);
    }

    /**
     * {@inheritdoc}
     */
    protected function doClear($namespace)
    {
        $ok = true;

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $ok = ($file->isDir() || @unlink($file) || !file_exists($file)) && $ok;
        }

        return $ok;
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete(array $ids)
    {
        $ok = true;

        foreach ($ids as $id) {
            $file = $this->getFile($id);
            $ok = (!file_exists($file) || @unlink($file) || !file_exists($file)) && $ok;
        }

        return $ok;
    }

    private function write($file, $data, $expiresAt = null)
    {
        if (false === @file_put_contents($this->tmp, $data)) {
            return false;
        }
        if (null !== $expiresAt) {
            @touch($this->tmp, $expiresAt);
        }

        if (@rename($this->tmp, $file)) {
            return true;
        }
        @unlink($this->tmp);

        return false;
    }

    private function getFile($id, $mkdir = false)
    {
        $hash = str_replace('/', '-', base64_encode(hash('sha256', static::class.$id, true)));
        $dir = $this->directory.strtoupper($hash[0].DIRECTORY_SEPARATOR.$hash[1].DIRECTORY_SEPARATOR);

        if ($mkdir && !file_exists($dir)) {
            @mkdir($dir, 0777, true);
        }

        return $dir.substr($hash, 2, 20);
    }
}
