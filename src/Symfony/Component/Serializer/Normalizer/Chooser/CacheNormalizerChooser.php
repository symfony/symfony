<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Normalizer\Chooser;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class CacheNormalizerChooser implements NormalizerChooserInterface
{
    private $decorated;
    private $cacheItemPool;
    private $loadedNormalizers;
    private $loadedDenormalizers;

    public function __construct(NormalizerChooserInterface $decorated, CacheItemPoolInterface $cacheItemPool)
    {
        $this->decorated = $decorated;
        $this->cacheItemPool = $cacheItemPool;
    }

    public function chooseNormalizer(array $normalizers, $data, ?string $format = null, array $context = []): ?NormalizerInterface
    {
        $type = \is_object($data) ? \get_class($data) : 'native-'.\gettype($data);
        $type = str_replace('\\', '-', $type);
        $key = $this->generateKey(true, $type, $format, $context);

        if (isset($this->loadedNormalizers[$key])) {
            return $this->loadedNormalizers[$key];
        }

        $item = $this->cacheItemPool->getItem($key);

        if ($item->isHit()) {
            return $this->loadedNormalizers[$key] = $normalizers[$item->get()];
        }

        $normalizer = $this->loadedNormalizers[$key] = $this->decorated->chooseNormalizer($normalizers, $data, $format, $context);
        $this->cacheItemPool->save($item->set(array_search($normalizer, $normalizers)));

        return $normalizer;
    }

    public function chooseDenormalizer(array $denormalizers, $data, string $class, ?string $format = null, array $context = []): ?DenormalizerInterface
    {
        $type = str_replace('\\', '-', $class);
        $key = $this->generateKey(false, $type, $format, $context);

        if (isset($this->loadedDenormalizers[$key])) {
            return $this->loadedDenormalizers[$key];
        }

        $item = $this->cacheItemPool->getItem($key);

        if ($item->isHit()) {
            return $this->loadedDenormalizers[$key] = $denormalizers[$item->get()];
        }

        $denormalizer = $this->loadedDenormalizers[$key] = $this->decorated->chooseDenormalizer($denormalizers, $data, $class, $format, $context);
        $this->cacheItemPool->save($item->set(array_search($denormalizer, $denormalizers)));

        return $denormalizer;
    }

    private function generateKey(bool $normalize, string $type, ?string $format, array $context = []): string
    {
        $compiledFormat = null !== $format ? '_'.$format : '';
        $compiledContext = count($context) ? '_'.hash('md5', json_encode($context)) : '';

        return sprintf('%s%s_%s%s', $normalize ? 'normalizer' : 'denormalizer', $compiledFormat, $type, $compiledContext);
    }
}
