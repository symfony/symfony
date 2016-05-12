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
 */
class FilesystemAdapter extends AbstractAdapter
{
    private $directory;

    public function __construct($namespace = '', $defaultLifetime = 0, $directory = null)
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
        if ('\\' === DIRECTORY_SEPARATOR && strlen($dir) > 234) {
            throw new InvalidArgumentException(sprintf('Cache directory too long (%s)', $directory));
        }

        $this->directory = $dir;
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch(array $ids)
    {
        $values = array();
        $now = time();

        foreach ($ids as $id) {
            $file = $this->getFile($id);
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
        $file = $this->getFile($id);

        return file_exists($file) && (@filemtime($file) > time() || $this->doFetch(array($id)));
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
     * {@inheritdoc}
     */
    protected function doSave(array $values, $lifetime)
    {
        $ok = true;
        $expiresAt = $lifetime ? time() + $lifetime : PHP_INT_MAX;
        $tmp = $this->directory.uniqid('', true);

        foreach ($values as $id => $value) {
            $file = $this->getFile($id, true);

            $value = $expiresAt."\n".rawurlencode($id)."\n".serialize($value);
            if (false !== @file_put_contents($tmp, $value)) {
                @touch($tmp, $expiresAt);
                $ok = @rename($tmp, $file) && $ok;
            } else {
                $ok = false;
            }
        }

        return $ok;
    }

    private function getFile($id, $mkdir = false)
    {
        $hash = str_replace('/', '-', base64_encode(md5($id, true)));
        $dir = $this->directory.$hash[0].DIRECTORY_SEPARATOR.$hash[1].DIRECTORY_SEPARATOR;

        if ($mkdir && !file_exists($dir)) {
            @mkdir($dir, 0777, true);
        }

        return $dir.substr($hash, 2, -2);
    }
}
