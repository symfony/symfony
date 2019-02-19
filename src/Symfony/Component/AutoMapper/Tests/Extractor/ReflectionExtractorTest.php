<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper\Tests\Extractor;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AutoMapper\Extractor\ReadAccessor;
use Symfony\Component\AutoMapper\Extractor\ReflectionExtractor;
use Symfony\Component\AutoMapper\Extractor\WriteMutator;
use Symfony\Component\AutoMapper\Tests\Fixtures\ReflectionExtractorTestFixture;

class ReflectionExtractorTest extends TestCase
{
    /** @var ReflectionExtractor */
    private $reflectionExtractor;

    public function setUp()
    {
        $this->reflectionExtractor = new ReflectionExtractor(true);
    }

    public function testReadAccessorGetter()
    {
        $accessor = $this->reflectionExtractor->getReadAccessor(ReflectionExtractorTestFixture::class, 'foo');

        self::assertNotNull($accessor);
        self::assertSame(ReadAccessor::TYPE_METHOD, $accessor->getType());
        self::assertSame('getFoo', $accessor->getName());
        self::assertFalse($accessor->isPrivate());
    }

    public function testReadAccessorGetterSetter()
    {
        $accessor = $this->reflectionExtractor->getReadAccessor(ReflectionExtractorTestFixture::class, 'bar');

        self::assertNotNull($accessor);
        self::assertSame(ReadAccessor::TYPE_METHOD, $accessor->getType());
        self::assertSame('bar', $accessor->getName());
        self::assertFalse($accessor->isPrivate());
    }

    public function testReadAccessorIsser()
    {
        $accessor = $this->reflectionExtractor->getReadAccessor(ReflectionExtractorTestFixture::class, 'baz');

        self::assertNotNull($accessor);
        self::assertSame(ReadAccessor::TYPE_METHOD, $accessor->getType());
        self::assertSame('isBaz', $accessor->getName());
        self::assertFalse($accessor->isPrivate());
    }

    public function testReadAccessorHasser()
    {
        $accessor = $this->reflectionExtractor->getReadAccessor(ReflectionExtractorTestFixture::class, 'foz');

        self::assertNotNull($accessor);
        self::assertSame(ReadAccessor::TYPE_METHOD, $accessor->getType());
        self::assertSame('hasFoz', $accessor->getName());
        self::assertFalse($accessor->isPrivate());
    }

    public function testReadAccessorMagicGet()
    {
        $accessor = $this->reflectionExtractor->getReadAccessor(ReflectionExtractorTestFixture::class, 'magicGet');

        self::assertNotNull($accessor);
        self::assertSame(ReadAccessor::TYPE_PROPERTY, $accessor->getType());
        self::assertSame('magicGet', $accessor->getName());
        self::assertFalse($accessor->isPrivate());
    }

    public function testWriteMutatorSetter()
    {
        $mutator = $this->reflectionExtractor->getWriteMutator(ReflectionExtractorTestFixture::class, 'foo');

        self::assertNotNull($mutator);
        self::assertSame(WriteMutator::TYPE_METHOD, $mutator->getType());
        self::assertSame('setFoo', $mutator->getName());
        self::assertFalse($mutator->isPrivate());
    }

    public function testWriteMutatorGetterSetter()
    {
        $mutator = $this->reflectionExtractor->getWriteMutator(ReflectionExtractorTestFixture::class, 'bar');

        self::assertNotNull($mutator);
        self::assertSame(WriteMutator::TYPE_METHOD, $mutator->getType());
        self::assertSame('bar', $mutator->getName());
        self::assertFalse($mutator->isPrivate());
    }

    public function testWriteMutatorMagicSet()
    {
        $mutator = $this->reflectionExtractor->getWriteMutator(ReflectionExtractorTestFixture::class, 'magicSet');

        self::assertNotNull($mutator);
        self::assertSame(WriteMutator::TYPE_PROPERTY, $mutator->getType());
        self::assertSame('magicSet', $mutator->getName());
        self::assertFalse($mutator->isPrivate());
    }

    public function testWriteMutatorConstructor()
    {
        $mutator = $this->reflectionExtractor->getWriteMutator(ReflectionExtractorTestFixture::class, 'propertyConstruct');

        self::assertNotNull($mutator);
        self::assertSame(WriteMutator::TYPE_CONSTRUCTOR, $mutator->getType());
        self::assertSame('propertyConstruct', $mutator->getName());
        self::assertFalse($mutator->isPrivate());
    }
}
