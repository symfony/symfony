<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\CacheWarmer;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Serializer\Cache\CacheNormalizationProviderInterface;
use Symfony\Component\Serializer\Normalizer\Chooser\CacheNormalizerChooser;
use Symfony\Component\Serializer\Normalizer\Chooser\NormalizerChooser;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class SerializerNormalizerChooserCacheWarmer extends AbstractPhpFileCacheWarmer
{
    private $normalizers;
    private $normalizationProviders;
    private $normalizer;
    private $denormalizer;

    public function __construct(array $normalizers, array $cacheNormalizationProviders, string $phpArrayFile, NormalizerInterface $normalizer, DenormalizerInterface $denormalizer)
    {
        parent::__construct($phpArrayFile);
        $this->normalizers = $normalizers;
        $this->normalizationProviders = $cacheNormalizationProviders;
        $this->normalizer = $normalizer;
        $this->denormalizer = $denormalizer;
    }

    protected function doWarmUp(string $cacheDir, ArrayAdapter $arrayAdapter)
    {
        $chooser = new CacheNormalizerChooser(new NormalizerChooser($this->normalizer, $this->denormalizer), $arrayAdapter);

        foreach ($this->normalizationProviders as $normalizationProvider) {
            if (!$normalizationProvider instanceof CacheNormalizationProviderInterface) {
                continue;
            }

            foreach ($normalizationProvider->provide() as $normalizationContext) {
                $format = $normalizationContext[0];
                $data = $normalizationContext[1];
                $context = $normalizationContext[2] ?? [];

                $chooser->chooseNormalizer($this->normalizers, $data, $format, $context);
                $chooser->chooseDenormalizer($this->normalizers, $data, get_class($data), $format, $context);
            }
        }

        return true;
    }
}
