<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Translation;

use Doctrine\Common\Cache\Cache;
use Symfony\Component\Translation\MessageCacheInterface;
use Symfony\Component\Translation\MessageCatalogueInterface;

/**
 * @author Abdellatif Ait Boudad <a.aitboudad@gmail.com>
 */
class DoctrineMessageCache implements MessageCacheInterface
{
    const CACHE_CATALOGUE_HASH = 'catalogue_hash';
    const CACHE_DUMP_TIME = 'time';
    const CACHE_META_DATA = 'meta';
    const CATALOGUE_FALLBACK_LOCALE = 'fallback_locale';

    /**
     * @var bool
     */
    private $debug;

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @param Cache $cache
     * @param bool  $debug
     */
    public function __construct(Cache $cache, $debug = false)
    {
        $this->cache = $cache;
        $this->debug = $debug;
    }

    /**
     * {@inheritdoc}
     */
    public function isFresh($locale, array $options = array())
    {
        $catalogueIdentifier = $this->getCatalogueIdentifier($locale, $options);
        if (!$this->cache->contains($this->getCatalogueHashKey($catalogueIdentifier))) {
            return false;
        }

        if ($this->debug) {
            $time = $this->cache->fetch($this->getDumpTimeKey($locale));
            $meta = unserialize($this->cache->fetch($this->getMetaDataKey($locale)));
            foreach ($meta as $resource) {
                if (!$resource->isFresh($time)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function load($locale, array $options = array())
    {
        $messages = new DoctrineMessageCatalogue($locale, $this->cache, $this->getCatalogueIdentifier($locale, $options));
        $catalogue = $messages;
        while ($fallbackLocale = $this->cache->fetch($this->getFallbackLocaleKey($catalogue->getLocale()))) {
            $fallback = new DoctrineMessageCatalogue($fallbackLocale, $this->cache, $this->getCatalogueIdentifier($locale, $options));
            $catalogue->addFallbackCatalogue($fallback);
            $catalogue = $fallback;
        }

        return $messages;
    }

    /**
     * {@inheritdoc}
     */
    public function dump(MessageCatalogueInterface $messages, array $options = array())
    {
        $resourcesHash = $this->getCatalogueIdentifier($messages->getLocale(), $options);
        while ($messages) {
            $catalogue = new DoctrineMessageCatalogue($messages->getLocale(), $this->cache, $resourcesHash);
            $catalogue->addCatalogue($messages);

            $this->dumpMetaDataCatalogue($messages->getLocale(), $messages->getResources(), $resourcesHash);
            if ($fallback = $messages->getFallbackCatalogue()) {
                $this->cache->save($this->getFallbackLocaleKey($messages->getLocale()), $fallback->getLocale());
            }

            $messages = $messages->getFallbackCatalogue();
        }
    }

    private function getCatalogueIdentifier($locale, $options)
    {
        return sha1(serialize(array(
            'resources' => $options['resources'],
            'fallback_locales' => $options['fallback_locales'],
        )));
    }

    private function dumpMetaDataCatalogue($locale, $metadata, $resourcesHash)
    {
        // $catalogueIdentifier = $this->getCatalogueIdentifier($locale, $options);
        $this->cache->save($this->getMetaDataKey($locale), serialize($metadata));
        $this->cache->save($this->getCatalogueHashKey($resourcesHash), $resourcesHash);
        $this->cache->save($this->getDumpTimeKey($locale), time());
    }

    private function getDumpTimeKey($locale)
    {
        return self::CACHE_DUMP_TIME.'_'.$locale;
    }

    private function getMetaDataKey($locale)
    {
        return self::CACHE_META_DATA.'_'.$locale;
    }

    private function getCatalogueHashKey($locale)
    {
        return self::CACHE_CATALOGUE_HASH.'_'.$locale;
    }

    private function getFallbackLocaleKey($locale)
    {
        return self::CATALOGUE_FALLBACK_LOCALE.'_'.$locale;
    }
}
