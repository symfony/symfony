<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\app\JsonEncoder\Dto;

use Symfony\Bundle\FrameworkBundle\Tests\Functional\app\JsonEncoder\RangeNormalizer;
use Symfony\Component\JsonEncoder\Attribute\Denormalizer;
use Symfony\Component\JsonEncoder\Attribute\EncodedName;
use Symfony\Component\JsonEncoder\Attribute\JsonEncodable;
use Symfony\Component\JsonEncoder\Attribute\Normalizer;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
#[JsonEncodable]
class Dummy
{
    #[EncodedName('@name')]
    #[Normalizer('strtoupper')]
    #[Denormalizer('strtolower')]
    public string $name = 'dummy';

    #[Normalizer(RangeNormalizer::class)]
    #[Denormalizer(RangeNormalizer::class)]
    public array $range = [10, 20];
}
