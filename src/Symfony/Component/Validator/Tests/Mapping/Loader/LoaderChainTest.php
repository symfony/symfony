<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Mapping\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\LoaderChain;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;

class LoaderChainTest extends TestCase
{
    public function testAllLoadersAreCalled()
    {
        $metadata = new ClassMetadata('\stdClass');

        $loader1 = self::createMock(LoaderInterface::class);
        $loader1->expects(self::once())
            ->method('loadClassMetadata')
            ->with(self::equalTo($metadata));

        $loader2 = self::createMock(LoaderInterface::class);
        $loader2->expects(self::once())
            ->method('loadClassMetadata')
            ->with(self::equalTo($metadata));

        $chain = new LoaderChain([
            $loader1,
            $loader2,
        ]);

        $chain->loadClassMetadata($metadata);
    }

    public function testReturnsTrueIfAnyLoaderReturnedTrue()
    {
        $metadata = new ClassMetadata('\stdClass');

        $loader1 = self::createMock(LoaderInterface::class);
        $loader1->expects(self::any())
            ->method('loadClassMetadata')
            ->willReturn(true);

        $loader2 = self::createMock(LoaderInterface::class);
        $loader2->expects(self::any())
            ->method('loadClassMetadata')
            ->willReturn(false);

        $chain = new LoaderChain([
            $loader1,
            $loader2,
        ]);

        self::assertTrue($chain->loadClassMetadata($metadata));
    }

    public function testReturnsFalseIfNoLoaderReturnedTrue()
    {
        $metadata = new ClassMetadata('\stdClass');

        $loader1 = self::createMock(LoaderInterface::class);
        $loader1->expects(self::any())
            ->method('loadClassMetadata')
            ->willReturn(false);

        $loader2 = self::createMock(LoaderInterface::class);
        $loader2->expects(self::any())
            ->method('loadClassMetadata')
            ->willReturn(false);

        $chain = new LoaderChain([
            $loader1,
            $loader2,
        ]);

        self::assertFalse($chain->loadClassMetadata($metadata));
    }
}
