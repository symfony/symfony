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
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\TrustStamp;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class TrustStampTest extends TestCase
{
    public function testTrusted()
    {
        $this->assertTrue(TrustStamp::trusted()->isTrusted());
    }

    public function testUntrusted()
    {
        $this->assertFalse(TrustStamp::untrusted()->isTrusted());
    }

    public function testAnUnserializedStampIsNotTrusted()
    {
        $this->assertFalse(unserialize(serialize(TrustStamp::trusted()))->isTrusted());
    }

    public function testADenormalizedStampIsNotTrusted()
    {
        $this->assertFalse((new ObjectNormalizer())->denormalize([], TrustStamp::class)->isTrusted());
    }

    public function testAnEnvelopeDispatchedInThisProcessIsTrusted()
    {
        $this->assertTrue(TrustStamp::isEnvelopeTrusted(new Envelope(new \stdClass())));
    }

    public function testAReceivedEnvelopeIsNotTrusted()
    {
        $this->assertFalse(TrustStamp::isEnvelopeTrusted(new Envelope(new \stdClass(), [new ReceivedStamp('async')])));
    }

    public function testTheLastTrustStampDecides()
    {
        $this->assertTrue(TrustStamp::isEnvelopeTrusted(new Envelope(new \stdClass(), [new ReceivedStamp('async'), TrustStamp::untrusted(), TrustStamp::trusted()])));
        $this->assertFalse(TrustStamp::isEnvelopeTrusted(new Envelope(new \stdClass(), [TrustStamp::trusted(), TrustStamp::untrusted()])));
    }
}
