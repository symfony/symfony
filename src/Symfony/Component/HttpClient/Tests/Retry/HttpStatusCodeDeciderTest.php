<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\Retry;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Retry\HttpStatusCodeDecider;

class HttpStatusCodeDeciderTest extends TestCase
{
    public function testShouldRetryStatusCode()
    {
        $decider = new HttpStatusCodeDecider([500]);

        self::assertTrue($decider->shouldRetry('GET', 'http://example.com/', [], 500, [], null));
    }

    public function testIsNotRetryableOk()
    {
        $decider = new HttpStatusCodeDecider([500]);

        self::assertFalse($decider->shouldRetry('GET', 'http://example.com/', [], 200, [], null));
    }
}
