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
use Symfony\Component\Validator\Exception\LogicException;
use Symfony\Component\Validator\Mapping\Factory\BlackHoleMetadataFactory;

class BlackHoleMetadataFactoryTest extends TestCase
{
    public function testGetMetadataForThrowsALogicException()
    {
        self::expectException(LogicException::class);
        $metadataFactory = new BlackHoleMetadataFactory();
        $metadataFactory->getMetadataFor('foo');
    }

    public function testHasMetadataForReturnsFalse()
    {
        $metadataFactory = new BlackHoleMetadataFactory();

        self::assertFalse($metadataFactory->hasMetadataFor('foo'));
    }
}
