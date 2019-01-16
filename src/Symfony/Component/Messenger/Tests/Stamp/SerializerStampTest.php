<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Stamp;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Stamp\SerializerStamp;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class SerializerStampTest extends TestCase
{
    public function testSerializable()
    {
        $stamp = new SerializerStamp([ObjectNormalizer::GROUPS => ['Default', 'Extra']]);

        $this->assertEquals($stamp, unserialize(serialize($stamp)));
    }
}
