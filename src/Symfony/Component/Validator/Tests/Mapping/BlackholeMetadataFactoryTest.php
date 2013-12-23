<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Mapping;

use Symfony\Component\Validator\Mapping\BlackholeMetadataFactory;

class BlackholeMetadataFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \LogicException
     */
    public function testGetMetadataForThrowsALogicException()
    {
        $metadataFactory = new BlackholeMetadataFactory();
        $metadataFactory->getMetadataFor('foo');
    }

    public function testHasMetadataForReturnsFalse()
    {
        $metadataFactory = new BlackholeMetadataFactory();

        $this->assertFalse($metadataFactory->hasMetadataFor('foo'));
    }
}
