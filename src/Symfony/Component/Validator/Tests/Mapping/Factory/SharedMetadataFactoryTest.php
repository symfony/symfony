<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Mapping\Factory;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;
use Symfony\Component\Validator\Mapping\Factory\SharedMetadataFactory;
use Symfony\Component\Validator\Tests\Fixtures\NestedAttribute\Entity;

class SharedMetadataFactoryTest extends TestCase
{
    public function testInstancesCreatedWithTheSameKeyShareTheMetadataTheyLoad()
    {
        $metadata = new ClassMetadata(Entity::class);

        $decorated = $this->createMock(MetadataFactoryInterface::class);
        $decorated
            ->expects($this->once())
            ->method('getMetadataFor')
            ->with(Entity::class)
            ->willReturn($metadata);

        $this->assertSame($metadata, (new SharedMetadataFactory($decorated, __METHOD__))->getMetadataFor(Entity::class));
        $this->assertSame($metadata, (new SharedMetadataFactory($decorated, __METHOD__))->getMetadataFor(new Entity()));
        $this->assertSame($metadata, (new SharedMetadataFactory($decorated, __METHOD__))->getMetadataFor('\\'.Entity::class));
    }

    public function testInstancesCreatedWithDifferentKeysDoNotShare()
    {
        $decorated = $this->createMock(MetadataFactoryInterface::class);
        $decorated
            ->expects($this->exactly(2))
            ->method('getMetadataFor')
            ->willReturnCallback(static fn () => new ClassMetadata(Entity::class));

        $metadata = (new SharedMetadataFactory($decorated, __METHOD__.'1'))->getMetadataFor(Entity::class);

        $this->assertNotSame($metadata, (new SharedMetadataFactory($decorated, __METHOD__.'2'))->getMetadataFor(Entity::class));
    }

    public function testValuesThatAreNotClassesPassThrough()
    {
        $decorated = $this->createMock(MetadataFactoryInterface::class);
        $decorated
            ->expects($this->exactly(2))
            ->method('getMetadataFor')
            ->with(123)
            ->willReturnCallback(static fn () => new ClassMetadata(Entity::class));

        $metadata = (new SharedMetadataFactory($decorated, __METHOD__))->getMetadataFor(123);

        $this->assertNotSame($metadata, (new SharedMetadataFactory($decorated, __METHOD__))->getMetadataFor(123));
    }

    public function testHasMetadataForIsDelegated()
    {
        $decorated = $this->createMock(MetadataFactoryInterface::class);
        $decorated
            ->expects($this->exactly(2))
            ->method('hasMetadataFor')
            ->with(Entity::class)
            ->willReturn(true);

        $this->assertTrue((new SharedMetadataFactory($decorated, __METHOD__))->hasMetadataFor(Entity::class));
        $this->assertTrue((new SharedMetadataFactory($decorated, __METHOD__))->hasMetadataFor(Entity::class));
    }
}
