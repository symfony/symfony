<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Context\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Context\Normalizer\BackedEnumNormalizerContextBuilder;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;

class BackedEnumNormalizerContextBuilderTest extends TestCase
{
    private BackedEnumNormalizerContextBuilder $contextBuilder;

    protected function setUp(): void
    {
        $this->contextBuilder = new BackedEnumNormalizerContextBuilder();
    }

    public function testWithers()
    {
        $context = $this->contextBuilder->withAllowInvalidValues(true)->toArray();
        self::assertSame([BackedEnumNormalizer::ALLOW_INVALID_VALUES => true], $context);

        $context = $this->contextBuilder->withAllowInvalidValues(false)->toArray();
        self::assertSame([BackedEnumNormalizer::ALLOW_INVALID_VALUES => false], $context);
    }
}
