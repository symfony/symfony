<?php

namespace Symfony\Component\HttpKernel\HttpCache;

use Doctrine\Common\Cache\CacheProvider;

/**
 * Filesystem cache driver.
 */
class FilesystemCache extends CacheProvider
{
    private $path;

    /**
     * Sets the path.
     *
     * @param string $path
     */
    public function setPath($path)
    {
        if (!is_dir($path) && !mkdir($path, 0777, true)) {
            throw new \RuntimeException('Unable to create the "'.$path.'" directory.');
        }

        $this->path = $path;
    }

    /**
     * {@inheritdoc}
     */
    protected function doContains($id)
    {
        return file_exists($this->getFullPath($id));
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch($id)
    {
        if (false === $content = @file_get_contents($this->getFullPath($id))) {
            return false;
        }

        return unserialize($content);
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave($id, $data, $lifeTime = false)
    {
        return (bool) @file_put_contents($this->getFullPath($id), serialize($data));
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete($id)
    {
        return @unlink($this->getFullPath($id));
    }

    /**
     * {@inheritdoc}
     */
    protected function doFlush()
    {
        $files = new \DirectoryIterator($this->path);
        foreach ($files as $file) {
            if ($file->isFile()) {
                @unlink($file->getRealPath());
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doGetStats()
    {
        return null;
    }

    private function getFullPath($id)
    {
        return $this->path . '/' . md5($id);
    }
}