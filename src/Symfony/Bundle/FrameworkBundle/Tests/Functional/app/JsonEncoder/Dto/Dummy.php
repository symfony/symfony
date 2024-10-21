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

use Symfony\Bundle\FrameworkBundle\Tests\Functional\app\JsonEncoder\LowercaseDenormalizer;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\app\JsonEncoder\UppercaseNormalizer;
use Symfony\Component\JsonEncoder\Attribute\Denormalizer;
use Symfony\Component\JsonEncoder\Attribute\EncodedName;
use Symfony\Component\JsonEncoder\Attribute\Normalizer;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
class Dummy
{
    #[EncodedName('@name')]
    #[Normalizer(UppercaseNormalizer::class)]
    #[Denormalizer(LowercaseDenormalizer::class)]
    public string $name = 'dummy';
}
