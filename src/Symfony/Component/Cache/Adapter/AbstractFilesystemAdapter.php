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

abstract class AbstractFilesystemAdapter extends AbstractAdapter
{
    /**
     * @var string
     */
    private $fileSuffix;

    /**
     * @var string
     */
    private $directory;

    public function __construct($namespace = '', $defaultLifetime = 0, $directory = null, $fileSuffix = '')
    {
        parent::__construct('', $defaultLifetime);

        if (!isset($directory[0])) {
            $directory = sys_get_temp_dir().'/symfony-cache';
        }
        if (isset($namespace[0])) {
            if (preg_match('#[^-+_.A-Za-z0-9]#', $namespace, $match)) {
                throw new InvalidArgumentException(sprintf('FilesystemAdapter namespace contains "%s" but only characters in [-+_.A-Za-z0-9] are allowed.', $match[0]));
            }
            $directory .= '/'.$namespace;
        }
        if (!file_exists($dir = $directory.'/.')) {
            @mkdir($directory, 0777, true);
        }
        if (false === $dir = realpath($dir)) {
            throw new InvalidArgumentException(sprintf('Cache directory does not exist (%s)', $directory));
        }
        if (!is_writable($dir .= DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException(sprintf('Cache directory is not writable (%s)', $directory));
        }
        // On Windows the whole path is limited to 258 chars
        if ('\\' === DIRECTORY_SEPARATOR && strlen($dir) + strlen($fileSuffix) > 234) {
            throw new InvalidArgumentException(sprintf('Cache directory too long (%s)', $directory));
        }

        $this->fileSuffix = $fileSuffix;
        $this->directory = $dir;
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

    /**
     * @param string   $id               Id of the cache item (used for obtaining file path to write to)
     * @param string   $fileContent      Content to write to file
     * @param int|null $modificationTime If this is not-null it will be passed to touch()
     *
     * @return bool
     */
    protected function saveFile($id, $fileContent, $modificationTime = null)
    {
        $temporaryFile = $this->directory.uniqid('', true);
        $file = $this->getFile($id, true);

        if (false === @file_put_contents($temporaryFile, $fileContent)) {
            return false;
        }

        if (null !== $modificationTime) {
            @touch($temporaryFile, $modificationTime);
        }

        return @rename($temporaryFile, $file);
    }

    protected function getFile($id, $mkdir = false)
    {
        $hash = str_replace('/', '-', base64_encode(md5($id, true)));
        $dir = $this->directory.$hash[0].DIRECTORY_SEPARATOR.$hash[1].DIRECTORY_SEPARATOR;

        if ($mkdir && !file_exists($dir)) {
            @mkdir($dir, 0777, true);
        }

        return $dir.substr($hash, 2, -2).$this->fileSuffix;
    }
}
