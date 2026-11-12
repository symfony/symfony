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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class StreamWrapperTest extends TestCase
{
    #[DataProvider('provideLastModified')]
    public function testMtimeIsReadFromLastModified(string $lastModified, int $mtime)
    {
        $client = new MockHttpClient(new MockResponse('body', ['response_headers' => ['Last-Modified' => $lastModified]]));

        $this->assertSame($mtime, fstat($client->request('GET', 'http://example.com/')->toStream())['mtime']);
    }

    public static function provideLastModified(): iterable
    {
        yield ['Wed, 21 Oct 2015 07:28:00 GMT', 1445412480];
        yield ['Sunday, 06-Nov-94 08:49:37 GMT', 784111777];
        yield ['Sun Nov  6 08:49:37 1994', 784111777];
        yield ['-1', 0];
        yield ['Thu, 21 Oct 2015 07:28:00 GMT', 0];
        yield ['Thu, 31 Nov 1994 08:49:37 GMT', 0];
        yield ['Sun, 06 Nov 1994 24:49:37 GMT', 0];
        yield ['Sun, 06 Nov 1994 08:60:37 GMT', 0];
        yield ['Sun, 06 Nov 1994 08:49:60 GMT', 0];
        yield ['Sun, 06 Nov 94 08:49:37 GMT', 0];
    }
}
