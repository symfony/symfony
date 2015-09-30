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
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\MessageCatalogueProvider\MessageCatalogueProviderInterface;
use Symfony\Component\Translation\MessageCatalogueProvider\ResourceMessageCatalogueProvider;

/**
 * @author Abdellatif Ait Boudad <a.aitboudad@gmail.com>
 */
class DoctrineMessageCatalogueProvider implements MessageCatalogueProviderInterface
{
    const CACHE_CATALOGUE_HASH = 'sf2_translation_catalogue';
    const CACHE_DUMP_TIME = 'time';
    const CACHE_META_DATA = 'meta';
    const CATALOGUE_FALLBACK_LOCALE = 'fallback_locales';

    /**
     * @var MessageCatalogueProviderInterface
     */
    private $messageCatalogueProvider;

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var bool
     */
    private $debug;

    /**
     * @var MessageCatalogueInterface[]
     */
    private $catalogues;

    /**
     * @param MessageCatalogueProviderInterface $messageCatalogueProvider The message catalogue provider to use for loading the catalogue.
     * @param Cache                             $cache
     * @param bool                              $debug
     */
    public function __construct(MessageCatalogueProviderInterface $messageCatalogueProvider, Cache $cache, $debug = false)
    {
        $this->messageCatalogueProvider = $messageCatalogueProvider;
        $this->cache = $cache;
        $this->debug = $debug;
    }

    /**
     * {@inheritdoc}
     */
    public function getCatalogue($locale)
    {
        if (isset($this->catalogues[$locale]) && $this->isFresh($locale)) {
            return $this->catalogues[$locale];
        }

        return $this->catalogues[$locale] = $this->dumpCatalogue($locale);
    }

    /**
     * {@inheritdoc}
     */
    public function dumpCatalogue($locale)
    {
        if ($this->isFresh($locale)) {
            return $this->loadCatalogue($locale);
        }

        $messages = $this->messageCatalogueProvider->getCatalogue($locale);
        $doctrineCatalogue = $this->createDoctrineCatalogueMessage($locale);
        $doctrineCatalogue->addCatalogue($messages);

        // dump catalogue metadata
        $this->dumpCatalogueMetaData($locale, $doctrineCatalogue->getResources());

        $fallbackLocales = array();
        $messages = $messages->getFallbackCatalogue();
        while ($messages) {
            $fallbackLocale = $messages->getLocale();
            $catalogue = $this->createDoctrineCatalogueMessage($fallbackLocale);
            $catalogue->addCatalogue($messages);

            // dump catalogue metadata
            $this->dumpCatalogueMetaData($fallbackLocale, $messages->getResources());

            $fallbackLocales[] = $fallbackLocale;
            $doctrineCatalogue->addFallbackCatalogue($catalogue);
            $messages = $messages->getFallbackCatalogue();
        }

        $this->cache->save($this->getFallbackLocaleKey($locale), serialize($fallbackLocales));

        return $doctrineCatalogue;
    }

    private function loadCatalogue($locale)
    {
        $catalogue = $this->createDoctrineCatalogueMessage($locale);
        $fallbackLocales = unserialize($this->cache->fetch($this->getFallbackLocaleKey($catalogue->getLocale())));
        if ($fallbackLocales) {
            foreach ($fallbackLocales as $fallbackLocale) {
                $fallback = $this->createDoctrineCatalogueMessage($fallbackLocale);
                $catalogue->addFallbackCatalogue($fallback);
            }
        }

        return $catalogue;
    }

    /**
     * {@inheritdoc}
     */
    private function isFresh($locale)
    {
        if (!$this->cache->contains($this->getCatalogueHashKey($locale))) {
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

    private function createDoctrineCatalogueMessage($locale)
    {
        return new DoctrineMessageCatalogue($locale, $this->cache, $this->getCatalogueHashKey($locale));
    }

    private function getCatalogueHashKey($locale)
    {
        return self::CACHE_CATALOGUE_HASH.'_'.$locale;
    }

    private function dumpCatalogueMetaData($locale, $metadata)
    {
        $this->cache->save($this->getCatalogueHashKey($locale), true);
        $this->cache->save($this->getDumpTimeKey($locale), time());
        $this->cache->save($this->getMetaDataKey($locale), serialize($metadata));
    }

    private function getDumpTimeKey($locale)
    {
        return $this->getCatalogueHashKey($locale).'_'.self::CACHE_DUMP_TIME;
    }

    private function getMetaDataKey($locale)
    {
        return $this->getCatalogueHashKey($locale).'_'.self::CACHE_META_DATA;
    }

    private function getFallbackLocaleKey($locale)
    {
        $key = self::CATALOGUE_FALLBACK_LOCALE;
        if ($this->messageCatalogueProvider instanceof ResourceMessageCatalogueProvider) {
            $key .= '_'.sha1(serialize($this->messageCatalogueProvider->getFallbackLocales()));
        }

        return $this->getCatalogueHashKey($locale).'_'.$key;
    }
}
