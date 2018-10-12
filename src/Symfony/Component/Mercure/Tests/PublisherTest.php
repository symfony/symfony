<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Mercure\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mercure\Publisher;
use Symfony\Component\Mercure\Update;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PublisherTest extends TestCase
{
    const URL = 'https://demo.mercure.rocks/publish';
    const JWT = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.e30.HB0k08BaV8KlLZ3EafCRlTDGbkd9qdznCzJQ_l8ELTU';

    public function testPublish()
    {
        $jwtProvider = function () {
            return self::JWT;
        };

        $httpClient = function (string $url, string $jwt, string $postData) {
            $this->assertSame(self::URL, $url);
            $this->assertSame(self::JWT, $jwt);
            $this->assertSame('topic=https%3A%2F%2Fdemo.mercure.rocks%2Fdemo%2Fbooks%2F1.jsonld&data=Hi+from+Symfony%21', $postData);
        };

        // Set $httpClient to null to dispatch a real update through the demo hub
        $publisher = new Publisher('https://demo.mercure.rocks/publish', $jwtProvider, $httpClient);
        $publisher(new Update('https://demo.mercure.rocks/demo/books/1.jsonld', 'Hi from Symfony!'));
    }
}
