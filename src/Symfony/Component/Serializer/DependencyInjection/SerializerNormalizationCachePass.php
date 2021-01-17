<?php

namespace Symfony\Component\Serializer\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SerializerNormalizationCachePass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    private $cacheWarmerService;
    private $normalizerTag;
    private $cacheNormalizationProviderTag;

    public function __construct(string $cacheWarmerService = 'serializer.normalizer_chooser.cache_warmer', string $normalizerTag = 'serializer.normalizer', string $cacheNormalizationProviderTag = 'serializer.normalizer_chooser.cache.provider')
    {
        $this->cacheWarmerService = $cacheWarmerService;
        $this->normalizerTag = $normalizerTag;
        $this->cacheNormalizationProviderTag = $cacheNormalizationProviderTag;
    }

    public function process(ContainerBuilder $container)
    {
        $cacheWarmer = $container->getDefinition($this->cacheWarmerService);
        $normalizers = $this->findAndSortTaggedServices($this->normalizerTag, $container);
        $providers = $this->findAndSortTaggedServices($this->cacheNormalizationProviderTag, $container);
        $cacheWarmer->setArgument(0, $normalizers);
        $cacheWarmer->setArgument(1, $providers);
    }
}
