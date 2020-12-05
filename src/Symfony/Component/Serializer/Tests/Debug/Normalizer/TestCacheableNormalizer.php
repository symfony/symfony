<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Debug\Normalizer;

use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class TestCacheableNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
{
    /**
     * @var bool
     */
    private $cacheable;

    public function __construct(bool $cacheable)
    {
        $this->cacheable = $cacheable;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return $this->cacheable;
    }

    public function normalize($object, string $format = null, array $context = [])
    {
    }

    public function supportsNormalization($data, string $format = null): bool
    {
        return true;
    }
}
