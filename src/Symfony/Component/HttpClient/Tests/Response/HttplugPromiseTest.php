<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\Response;

use GuzzleHttp\Promise\Promise;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Response\HttplugPromise;

class HttplugPromiseTest extends TestCase
{
    public function testComplexNesting()
    {
        $mkPromise = function ($result): HttplugPromise {
            $guzzlePromise = new Promise(function () use (&$guzzlePromise, $result) {
                $guzzlePromise->resolve($result);
            });

            return new HttplugPromise($guzzlePromise);
        };

        $promise1 = $mkPromise('result');
        $promise2 = $promise1->then($mkPromise);
        $promise3 = $promise2->then(fn ($result) => $result);

        $this->assertSame('result', $promise3->wait());
    }
}
