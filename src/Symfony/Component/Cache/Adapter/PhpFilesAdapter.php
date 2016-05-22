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

class PhpFilesAdapter extends AbstractAdapter
{
    /**
     * @var FilesCacheHelper
     */
    protected $filesCacheHelper;

    public function __construct($namespace = '', $defaultLifetime = 0, $directory = null)
    {
        parent::__construct($namespace, $defaultLifetime);
        $this->filesCacheHelper = new FilesCacheHelper($directory, '.php');
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch(array $ids)
    {
        $values = array();

        foreach ($ids as $id) {
            $valueArray = $this->includeCacheFileWithSequenceCheck($this->filesCacheHelper->getFilePath($id));
            if (!is_array($valueArray)) {
                continue;
            }
            list($value, $expiresAt, ) = $valueArray;
            if (time() < (int) $expiresAt) {
                $values[$id] = $value;
            }
        }

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    protected function doHave($id)
    {
        return 0 !== count($this->doFetch(array($id)));
    }

    /**
     * {@inheritdoc}
     */
    protected function doClear($namespace)
    {
        $ok = true;
        $directory = $this->filesCacheHelper->getDirectory();

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $ok = ($file->isDir() || $this->removeCacheFile((string)$file)) && $ok;
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
            $ok = $this->removeCacheFile($file) && $ok;
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
            $file = $this->filesCacheHelper->getFilePath($id, true);
            $ok = $this->saveCacheFile($file, $value, $expiresAt) && $ok;
        }

        return $ok;
    }

    /**
     * @param string $file
     * @param mixed  $value
     * @param int    $expiresAt
     *
     * @return bool
     */
    private function saveCacheFile($file, $value, $expiresAt)
    {
        $currentSequenceNumber = 1;
        if (defined('HHVM_VERSION')) {
            // workaround for https://github.com/facebook/hhvm/issues/1447 and similar
            // use file modification as a sequence number (cache file versioning)
            @clearstatcache(true, $file);
            $currentSequenceNumber = 1 + (int)@filemtime($file);
            if ($currentSequenceNumber > time()) {
                $currentSequenceNumber = 1;
            }
        }
        $fileContent = $this->createCacheFileContent($value, $expiresAt, $currentSequenceNumber);
        $ok = $this->filesCacheHelper->saveFile($file, $fileContent, $currentSequenceNumber);
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
        if (function_exists('apc_compile_file')) {
            @apc_compile_file($file);
        }
        return $ok;
    }

    /**
     * @param string $file
     *
     * @return bool
     */
    private function removeCacheFile($file)
    {
        if (defined('HHVM_VERSION')) {
            // workaround for https://github.com/facebook/hhvm/issues/1447
            // save file with empty expired data instead of removing it
            // so that we can check modification time (where we store version number)
            return $this->saveCacheFile($file, null, 0);
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
        if (function_exists('apc_delete_file')) {
            @apc_delete_file($file);
        }
        @unlink($file);
        return !@file_exists($file);
    }

    /**
     * @param string $file File path
     *
     * @return mixed|null
     */
    private function includeCacheFileWithSequenceCheck($file)
    {
        $valueArray = $this->includeCacheFile($file);

        if (!is_array($valueArray) || 3 !== count($valueArray)) {
            return;
        }

        list(, , $foundSequenceNumber) = $valueArray;

        if (defined('HHVM_VERSION')) {
            // workaround for https://github.com/facebook/hhvm/issues/1447 and similar
            // use file modification as a sequence number (cache file version)
            @clearstatcache(true, $file);
            $actualSequenceNumber = (int)@filemtime($file);
            if ($foundSequenceNumber != $actualSequenceNumber) {
                return $this->includeCacheFile($file, true);
            }
        }

        return $valueArray;
    }

    /**
     * @param string $file    File path
     * @param bool   $useEval If true, tries to eval file contents instead of including it (forcing omitting opcache).
     *
     * @return mixed|null
     */
    private function includeCacheFile($file, $useEval = false)
    {
        if ($useEval) {
            $content = @file_get_contents($file);
            if ($content) {
                $valueArray = eval('?>'.$content);
            } else {
                return;
            }
        } else {
            $valueArray = @include $file;
        }

        return $valueArray;
    }

    /**
     * @param mixed $value
     * @param int   $expiresAt
     * @param int   $currentSequenceNumber
     *
     * @return string
     */
    private function createCacheFileContent($value, $expiresAt, $currentSequenceNumber)
    {
        $exportedValue = var_export(serialize(array($value, $expiresAt, $currentSequenceNumber)), true);

        return '<?php return unserialize('.$exportedValue.');';
    }
}
