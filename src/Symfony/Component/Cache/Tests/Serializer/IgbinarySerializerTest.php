<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Serializer;

use Symfony\Component\Cache\Serializer\IgbinarySerializer;
use Symfony\Component\Cache\SerializerInterface;

class IgbinarySerializerTest extends SerializerTest
{
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        if (!extension_loaded('igbinary')) {
            self::markTestSkipped('Extension igbinary is not loaded.');
        }
    }

    protected function createSerializer(): SerializerInterface
    {
        return new IgbinarySerializer();
    }
}
