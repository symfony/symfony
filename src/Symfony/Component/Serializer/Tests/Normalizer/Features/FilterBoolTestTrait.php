<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer\Features;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Test AbstractNormalizer::FILTER_BOOL.
 */
trait FilterBoolTestTrait
{
    abstract protected function getNormalizerForFilterBool(): DenormalizerInterface;

    /**
     * @dataProvider provideObjectWithBoolArguments
     */
    public function testObjectWithBoolArguments(?bool $expectedValue, ?string $parameterValue)
    {
        $normalizer = $this->getNormalizerForFilterBool();

        $dummy = $normalizer->denormalize(['value' => $parameterValue], FilterBoolObject::class, context: ['filter_bool' => true]);

        $this->assertSame($expectedValue, $dummy->value);
    }

    public static function provideObjectWithBoolArguments()
    {
        yield 'default value' => [null, null];
        yield '0' => [false, '0'];
        yield 'false' => [false, 'false'];
        yield 'no' => [false, 'no'];
        yield 'off' => [false, 'off'];
        yield '1' => [true, '1'];
        yield 'true' => [true, 'true'];
        yield 'yes' => [true, 'yes'];
        yield 'on' => [true, 'on'];
    }
}
