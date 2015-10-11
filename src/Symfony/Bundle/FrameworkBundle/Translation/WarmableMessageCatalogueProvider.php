<?php

namespace Symfony\Bundle\FrameworkBundle\Translation;

use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Translation\MessageCatalogueProvider\MessageCatalogueProviderInterface;
use Symfony\Component\Translation\MessageCatalogueProvider\ResourceMessageCatalogueProvider;
use Symfony\Component\Translation\MessageCatalogueProvider\CachedMessageCatalogueProvider;

class WarmableMessageCatalogueProvider implements MessageCatalogueProviderInterface, WarmableInterface
{
    /**
     * @var MessageCatalogueProviderInterface
     */
    private $messageCatalogueProvider;

    /**
     * @var ResourceMessageCatalogueProvider
     */
    private $resourceMessageCatalogueProvider;

    public function __construct(MessageCatalogueProviderInterface $messageCatalogueProvider, ResourceMessageCatalogueProvider $resourceMessageCatalogueProvider)
    {
        $this->messageCatalogueProvider = $messageCatalogueProvider;
        $this->resourceMessageCatalogueProvider = $resourceMessageCatalogueProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function warmUp($cacheDir)
    {
        // skip warmUp when translator doesn't use cache
        if (!$this->messageCatalogueProvider instanceof CachedMessageCatalogueProvider) {
            return;
        }

        $locales = array_merge($this->resourceMessageCatalogueProvider->getFallbackLocales(), array_keys($this->resourceMessageCatalogueProvider->getResources()));
        foreach (array_unique($locales) as $locale) {
            $this->getCatalogue($locale);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCatalogue($locale)
    {
        return $this->messageCatalogueProvider->getCatalogue($locale);
    }
}
