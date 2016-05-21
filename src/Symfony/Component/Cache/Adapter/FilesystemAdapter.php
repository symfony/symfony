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

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class FilesystemAdapter extends AbstractFilesystemAdapter
{
    public function __construct($namespace = '', $defaultLifetime = 0, $directory = null)
    {
        parent::__construct('', $defaultLifetime, $directory);
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
    protected function doSave(array $values, $lifetime)
    {
        $ok = true;
        $expiresAt = $lifetime ? time() + $lifetime : PHP_INT_MAX;

        foreach ($values as $id => $value) {
            $fileContent = $this->getCacheFileContent($id, $value, $expiresAt);
            $ok = $this->saveFile($id, $fileContent, $expiresAt) && $ok;
        }

        return $ok;
    }

    /**
     * @inheritdoc
     */
    protected function getCacheFileContent($id, $value, $expiresAt)
    {
        return $expiresAt."\n".rawurlencode($id)."\n".serialize($value);
    }
}
