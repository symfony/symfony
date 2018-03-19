<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Traits;

use Symfony\Component\Cache\Exception\CacheException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Rob Frawley 2nd <rmf@src.run>
 *
 * @internal
 */
trait FilesystemTrait
{
    use FilesystemCommonTrait;

    /**
     * @return bool
     */
    public function prune()
    {
        $time = time();
        $pruned = true;

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
            if (!$h = @fopen($file, 'rb')) {
                continue;
            }

            if (($expiresAt = (int) fgets($h)) && $time >= $expiresAt) {
                fclose($h);
                $pruned = @unlink($file) && !file_exists($file) && $pruned;
            } else {
                fclose($h);
            }
        }

        return $pruned;
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
            if (!file_exists($file) || !$h = @fopen($file, 'rb')) {
                continue;
            }
            if (($expiresAt = (int) fgets($h)) && $now >= $expiresAt) {
                fclose($h);
                @unlink($file);
            } else {
                $i = rawurldecode(rtrim(fgets($h)));
                $value = stream_get_contents($h);
                fclose($h);
                if ($i === $id) {
                    $values[$id] = parent::unserialize($value);
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
    protected function doSave(array $values, $lifetime)
    {
        $ok = true;
        $expiresAt = $lifetime ? (time() + $lifetime) : 0;

        foreach ($values as $id => $value) {
            $ok = $this->write($this->getFile($id, true), $expiresAt."\n".rawurlencode($id)."\n".serialize($value), $expiresAt) && $ok;
        }

        if (!$ok && !is_writable($this->directory)) {
            throw new CacheException(sprintf('Cache directory is not writable (%s)', $this->directory));
        }

        return $ok;
    }
}
