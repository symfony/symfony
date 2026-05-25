<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Tests\Policy;

use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\Policy\TokenBucket;

class TokenBucketTestToStringGadget
{
    public static bool $fired = false;

    public function __toString(): string
    {
        self::$fired = true;

        return '';
    }
}

class TokenBucketTest extends TestCase
{
    public function testUnserializeRejectsObjectInTypedIdProperty()
    {
        $data = [
            'id' => new TokenBucketTestToStringGadget(),
            'tokens' => 0,
            'timer' => 0.0,
            'burstSize' => 10,
            'rate' => 'PT1M-10',
        ];
        $payload = \sprintf('O:%d:"%s":%d:{', \strlen(TokenBucket::class), TokenBucket::class, \count($data));
        foreach ($data as $key => $value) {
            $payload .= serialize($key).serialize($value);
        }
        $payload .= '}';
        TokenBucketTestToStringGadget::$fired = false;

        try {
            unserialize($payload);
            $this->fail('Expected BadMethodCallException.');
        } catch (\BadMethodCallException $e) {
        }

        $this->assertFalse(TokenBucketTestToStringGadget::$fired, '__toString gadget must not fire during unserialize');
    }
}
