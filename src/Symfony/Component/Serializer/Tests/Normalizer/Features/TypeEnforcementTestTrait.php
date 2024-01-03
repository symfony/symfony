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

use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Test type mismatches with a denormalizer that is aware of types.
 * Covers AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT.
 */
trait TypeEnforcementTestTrait
{
    abstract protected function getDenormalizerForTypeEnforcement(): DenormalizerInterface;

    public function testRejectInvalidType()
    {
        $denormalizer = $this->getDenormalizerForTypeEnforcement();

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('The type of the "date" attribute for class "'.ObjectOuter::class.'" must be one of "DateTimeInterface" ("string" given).');
        $denormalizer->denormalize(['date' => 'foo'], ObjectOuter::class);
    }

    public function testRejectInvalidKey()
    {
        $denormalizer = $this->getDenormalizerForTypeEnforcement();

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('The type of the key "a" must be "int" ("string" given).');
        $denormalizer->denormalize(['inners' => ['a' => ['foo' => 1]]], ObjectOuter::class);
    }

    public function testDoNotRejectInvalidTypeOnDisableTypeEnforcementContextOption()
    {
        $denormalizer = $this->getDenormalizerForTypeEnforcement();

        $this->assertSame('foo', $denormalizer->denormalize(
            ['number' => 'foo'],
            TypeEnforcementNumberObject::class,
            null,
            [AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true]
        )->number);
    }
}
