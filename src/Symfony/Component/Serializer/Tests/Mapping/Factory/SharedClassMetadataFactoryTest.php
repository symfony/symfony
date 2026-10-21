<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Mapping\Factory;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Mapping\Factory\SharedClassMetadataFactory;
use Symfony\Component\Serializer\Tests\Fixtures\Dummy;

class SharedClassMetadataFactoryTest extends TestCase
{
    public function testInstancesCreatedWithTheSameKeyShareTheMetadataTheyLoad()
    {
        $metadata = new ClassMetadata(Dummy::class);

        $decorated = $this->createMock(ClassMetadataFactoryInterface::class);
        $decorated
            ->expects($this->once())
            ->method('getMetadataFor')
            ->with(Dummy::class)
            ->willReturn($metadata);

        $this->assertSame($metadata, (new SharedClassMetadataFactory($decorated, __METHOD__))->getMetadataFor(Dummy::class));
        $this->assertSame($metadata, (new SharedClassMetadataFactory($decorated, __METHOD__))->getMetadataFor(new Dummy()));
        $this->assertSame($metadata, (new SharedClassMetadataFactory($decorated, __METHOD__))->getMetadataFor('\\'.Dummy::class));
    }

    public function testInstancesCreatedWithDifferentKeysDoNotShare()
    {
        $decorated = $this->createMock(ClassMetadataFactoryInterface::class);
        $decorated
            ->expects($this->exactly(2))
            ->method('getMetadataFor')
            ->willReturnCallback(static fn () => new ClassMetadata(Dummy::class));

        $metadata = (new SharedClassMetadataFactory($decorated, __METHOD__.'1'))->getMetadataFor(Dummy::class);

        $this->assertNotSame($metadata, (new SharedClassMetadataFactory($decorated, __METHOD__.'2'))->getMetadataFor(Dummy::class));
    }

    public function testAClassThatDoesNotExistReachesTheDecoratedFactoryEveryTime()
    {
        $decorated = $this->createMock(ClassMetadataFactoryInterface::class);
        $decorated
            ->expects($this->exactly(2))
            ->method('getMetadataFor')
            ->with('Not\Exist')
            ->willThrowException(new InvalidArgumentException());

        for ($i = 0; $i < 2; ++$i) {
            try {
                (new SharedClassMetadataFactory($decorated, __METHOD__))->getMetadataFor('Not\Exist');
                $this->fail('An exception should have been thrown.');
            } catch (InvalidArgumentException) {
            }
        }
    }

    public function testHasMetadataForIsDelegated()
    {
        $decorated = $this->createMock(ClassMetadataFactoryInterface::class);
        $decorated
            ->expects($this->exactly(2))
            ->method('hasMetadataFor')
            ->with(Dummy::class)
            ->willReturn(true);

        $this->assertTrue((new SharedClassMetadataFactory($decorated, __METHOD__))->hasMetadataFor(Dummy::class));
        $this->assertTrue((new SharedClassMetadataFactory($decorated, __METHOD__))->hasMetadataFor(Dummy::class));
    }
}
