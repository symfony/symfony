<?php

namespace Symfony\Component\Messenger\Tests\Stamp;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Stamp\UniqueStamp;

/**
 * @author RJ Garcia <ragboyjr@icloud.com>
 */
class UniqueStampTest extends TestCase
{
    public function testStampCreation()
    {
        $stamp = new UniqueStamp('1234');
        $this->assertEquals('1234', $stamp->getId());
    }

    /** @dataProvider provideStampsForSerialization */
    public function testStampIsSerializable(UniqueStamp $stamp)
    {
        $this->assertEquals($stamp, unserialize(serialize($stamp)));
    }

    public function provideStampsForSerialization()
    {
        yield 'Stamp without id' => [new UniqueStamp()];
        yield 'Stamp with id' => [new UniqueStamp('12345')];
    }
}
