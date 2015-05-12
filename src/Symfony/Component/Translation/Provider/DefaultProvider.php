<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Provider;

use Symfony\Component\Translation\Exception\NotFoundResourceException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueInterface;

/**
 * The default implementation for MessageCatalogueProviderInterface.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class DefaultProvider implements MessageCatalogueProviderInterface
{
    /**
     * @var LoaderInterface[]
     */
    private $loaders = array();

    /**
     * @var array
     */
    private $resources = array();

    public function addLoader($format, LoaderInterface $loader)
    {
        $this->loaders[$format] = $loader;
    }

    public function getLoaders()
    {
        return $this->loaders;
    }

    public function addResource($format, $resource, $locale, $domain = null)
    {
        if (null === $domain) {
            $domain = 'messages';
        }

        $this->resources[$locale][] = array($format, $resource, $domain);
    }

    public function provideCatalogue($locale, $fallbackLocales = array())
    {
        $catalogue = new MessageCatalogue($locale);

        try {
            $this->doLoadCatalogue($catalogue, $locale);
        } catch (NotFoundResourceException $e) {
            if (!$fallbackLocales) {
                throw $e;
            }
        }
        $this->loadFallbackCatalogues($catalogue, $fallbackLocales);

        return $catalogue;
    }

    private function doLoadCatalogue(MessageCatalogueInterface $catalogue, $locale)
    {
        if (isset($this->resources[$locale])) {
            foreach ($this->resources[$locale] as $resource) {
                if (!isset($this->loaders[$resource[0]])) {
                    throw new \RuntimeException(sprintf('The "%s" translation loader is not registered.', $resource[0]));
                }
                $catalogue->addCatalogue($this->loaders[$resource[0]]->load($resource[1], $locale, $resource[2]));
            }
        }
    }

    private function loadFallbackCatalogues(MessageCatalogueInterface $catalogue, $fallbackLocales)
    {
        $current = $catalogue;

        foreach ($fallbackLocales as $fallback) {
            $fallbackCatalogue = new MessageCatalogue($fallback);
            $this->doLoadCatalogue($fallbackCatalogue, $fallback);
            $current->addFallbackCatalogue($fallbackCatalogue);
            $current = $fallbackCatalogue;
        }
    }
}
