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
use Symfony\Component\Serializer\Context\Normalizer\PropertyNormalizerContextBuilder;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;

/**
 * @author Antoine Lamirault <lamiraultantoine@gmail.com>
 */
class PropertyNormalizerContextBuilderTest extends TestCase
{
    private PropertyNormalizerContextBuilder $contextBuilder;

    protected function setUp(): void
    {
        $this->contextBuilder = new PropertyNormalizerContextBuilder();
    }

    public function testWithNormalizeVisibility()
    {
        $context = $this->contextBuilder
            ->withNormalizeVisibility(PropertyNormalizer::NORMALIZE_PUBLIC | PropertyNormalizer::NORMALIZE_PROTECTED)
            ->toArray();

        $this->assertSame([
            PropertyNormalizer::NORMALIZE_VISIBILITY => PropertyNormalizer::NORMALIZE_PUBLIC | PropertyNormalizer::NORMALIZE_PROTECTED,
        ], $context);
    }
}
