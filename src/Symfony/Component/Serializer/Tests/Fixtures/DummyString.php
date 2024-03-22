<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Serializer\Normalizer\DenormalizableInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * @author Jeroen <github.com/Jeroeny>
 */
class DummyString implements DenormalizableInterface
{
    /** @var string $value */
    public $value;

    public function denormalize(DenormalizerInterface $denormalizer, $data, ?string $format = null, array $context = []): void
    {
        $this->value = $data;
    }
}
