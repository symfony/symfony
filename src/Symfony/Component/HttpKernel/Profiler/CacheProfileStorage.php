<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Profiler;

use Psr\Cache\CacheItemPoolInterface;

class CacheProfileStorage extends AbstractProfileStorage
{
    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    public function __construct(CacheItemPoolInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * {@inheritdoc}
     */
    public function find($ip, $url, $limit, $method, $start = null, $end = null, $statusCode = null)
    {
        $item = $this->cache->getItem('index');
        if (!$item->isHit()) {
            return array();
        }

        $result = array();
        foreach ($item->get() as $values) {
            if (\count($result) >= $limit) {
                break;
            }

            $checked = $this->checkIndexItem($values, $ip, $url, $method, $start, $end, $statusCode);
            if (!$checked) {
                continue;
            }

            $result[$checked['token']] = $checked;
        }

        return array_values($result);
    }

    public function readProfileData($token)
    {
        if (!$token) {
            return false;
        }

        $item = $this->cache->getItem($token);
        if (!$item->isHit()) {
            return false;
        }

        return $item->get();
    }

    /**
     * {@inheritdoc}
     */
    public function write(Profile $profile)
    {
        $this->addToIndex($profile);

        $item = $this->cache->getItem($profile->getToken());
        $item->set($this->getProfileData($profile));

        return $this->cache->save($item);
    }

    /**
     * {@inheritdoc}
     */
    public function purge()
    {
        $this->cache->clear();
    }

    /**
     * Stores the profile in the index.
     */
    private function addToIndex(Profile $profile)
    {
        $item = $this->cache->getItem('index');
        if ($item->isHit()) {
            $index = $item->get();
        } else {
            $index = array();
        }
        $index[] = $this->getProfileIndexItem($profile);

        $item->set($index);
        $this->cache->save($item);
    }
}
