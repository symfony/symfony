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

use Symfony\Component\Cache\Serializer\PhpSerializer;
use Symfony\Component\Cache\SerializerInterface;

class PhpSerializerTest extends SerializerTest
{
    protected function createSerializer(): SerializerInterface
    {
        return new PhpSerializer();
    }
}
