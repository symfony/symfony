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

use Symfony\Component\Cache\CacheItem;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class FilesystemAdapter extends AbstractTagsInvalidatingAdapter
{
    use FilesystemAdapterTrait;

    public function __construct($namespace = '', $defaultLifetime = 0, $directory = null)
    {
        parent::__construct('', $defaultLifetime);
        $this->init($namespace, $directory);
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateTags($tags)
    {
        $ok = true;

        foreach (CacheItem::normalizeTags($tags) as $tag) {
            $ok = $this->doInvalidateTag($tag) && $ok;
        }

        return $ok;
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
    protected function doSaveWithTags(array $values, $lifetime, array $tags)
    {
        $ok = true;
        $expiresAt = $lifetime ? time() + $lifetime : PHP_INT_MAX;
        $newTags = $oldTags = array();

        foreach ($values as $id => $value) {
            $newIdTags = $tags[$id];
            $file = $this->getFile($id, true);
            $tagFile = $this->getFile($id.':tag', $newIdTags);
            $hasFile = file_exists($file);

            if ($hadTags = file_exists($tagFile)) {
                foreach (file($tagFile, FILE_IGNORE_NEW_LINES) as $tag) {
                    if (isset($newIdTags[$tag = rawurldecode($tag)])) {
                        if ($hasFile) {
                            unset($newIdTags[$tag]);
                        }
                    } else {
                        $oldTags[] = $tag;
                    }
                }
                if ($oldTags) {
                    $this->removeTags($id, $oldTags);
                    $oldTags = array();
                }
            }
            foreach ($newIdTags as $tag) {
                $newTags[$tag][] = $id;
            }

            $ok = $this->write($this->getFile($id, true), $expiresAt."\n".rawurlencode($id)."\n".serialize($value), $expiresAt) && $ok;

            if ($tags[$id]) {
                $ok = $this->write($tagFile, implode("\n", array_map('rawurlencode', $tags[$id]))."\n") && $ok;
            } elseif ($hadTags) {
                @unlink($tagFile);
            }
        }
        if ($newTags) {
            $ok = $this->doTag($newTags) && $ok;
        }

        return $ok;
    }

    private function doTag(array $tags)
    {
        $ok = true;
        $linkedTags = array();

        foreach ($tags as $tag => $ids) {
            $file = $this->getFile($tag, true);
            $linkedTags[$tag] = file_exists($file) ?: null;
            $h = fopen($file, 'ab');

            foreach ($ids as $id) {
                $ok = fwrite($h, rawurlencode($id)."\n") && $ok;
            }
            fclose($h);

            while (!isset($linkedTags[$tag]) && 0 < $r = strrpos($tag, '/')) {
                $linkedTags[$tag] = true;
                $parent = substr($tag, 0, $r);
                $file = $this->getFile($parent, true);
                $linkedTags[$parent] = file_exists($file) ?: null;
                $ok = file_put_contents($file, rawurlencode($tag)."\n", FILE_APPEND) && $ok;
                $tag = $parent;
            }
        }

        return $ok;
    }

    private function doInvalidateTag($tag)
    {
        if (!$h = @fopen($this->getFile($tag), 'r+b')) {
            return true;
        }
        $ok = true;
        $count = 0;

        while (false !== $id = fgets($h)) {
            if ('!' === $id[0]) {
                continue;
            }
            $id = rawurldecode(substr($id, 0, -1));

            if ('/' === $id[0]) {
                $ok = $this->doInvalidateTag($id) && $ok;
            } elseif (file_exists($file = $this->getFile($id))) {
                $count += $unlink = @unlink($file);
                $ok = ($unlink || !file_exists($file)) && $ok;
            }
        }

        ftruncate($h, 0);
        fclose($h);
        CacheItem::log($this->logger, 'Invalidating {count} items tagged as "{tag}"', array('tag' => $tag, 'count' => $count));

        return $ok;
    }

    private function removeTags($id, $tags)
    {
        $idLine = rawurlencode($id)."\n";
        $idSeek = -strlen($idLine);

        foreach ($tags as $tag) {
            if (!$h = @fopen($this->getFile($tag), 'r+b')) {
                continue;
            }
            while (false !== $line = fgets($h)) {
                if ($line === $idLine) {
                    fseek($h, $idSeek, SEEK_CUR);
                    fwrite($h, '!');
                }
            }
            fclose($h);
        }
    }
}
