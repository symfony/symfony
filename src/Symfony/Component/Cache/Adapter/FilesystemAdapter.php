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

use Symfony\Component\Cache\Adapter\Helper\FilesCacheHelper;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class FilesystemAdapter extends AbstractAdapter
{
    /**
     * @var FilesCacheHelper
     */
    protected $filesCacheHelper;

    /**
     * @param string $namespace       Cache namespace
     * @param int    $defaultLifetime Default lifetime for cache items
     * @param null   $directory       Path where cache items should be stored, defaults to sys_get_temp_dir().'/symfony-cache'
     */
    public function __construct($namespace = '', $defaultLifetime = 0, $directory = null)
    {
        parent::__construct($namespace, $defaultLifetime);
        $this->filesCacheHelper = new FilesCacheHelper($directory, $namespace);
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch(array $ids)
    {
        $values = array();
        $now = time();

        foreach ($ids as $id) {
            $file = $this->filesCacheHelper->getFilePath($id);
            if (!$h = @fopen($file, 'rb')) {
                continue;
            }
            if ($now >= (int) $expiresAt = fgets($h)) {
                fclose($h);
                if (isset($expiresAt[0])) {
                    @unlink($file);
                }
            } else {
                $i = rawurldecode(rtrim(fgets($h)));
                $value = stream_get_contents($h);
                fclose($h);
                if ($i === $id) {
                    $values[$id] = unserialize($value);
                }
            }
        }

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    protected function doHave($id)
    {
        $file = $this->filesCacheHelper->getFilePath($id);

        return file_exists($file) && (@filemtime($file) > time() || $this->doFetch(array($id)));
    }

    /**
     * {@inheritdoc}
     */
    protected function doClear($namespace)
    {
        $ok = true;
        $directory = $this->filesCacheHelper->getDirectory();

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
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
            $file = $this->filesCacheHelper->getFilePath($id);
            $ok = (!file_exists($file) || @unlink($file) || !file_exists($file)) && $ok;
        }

        return $ok;
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave(array $values, $lifetime)
    {
        $ok = true;
        $expiresAt = $lifetime ? time() + $lifetime : PHP_INT_MAX;

        foreach ($values as $id => $value) {
            $fileContent = $this->createCacheFileContent($id, $value, $expiresAt);
            $ok = $this->filesCacheHelper->saveFileForId($id, $fileContent, $expiresAt) && $ok;
        }

        return $ok;
    }

    /**
     * @param string $id
     * @param mixed  $value
     * @param int    $expiresAt
     *
     * @return string
     */
    protected function createCacheFileContent($id, $value, $expiresAt)
    {
        return $expiresAt."\n".rawurlencode($id)."\n".serialize($value);
    }
}
