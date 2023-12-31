<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\UrlParser;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\UrlParser\UrlParser;
use Symfony\Component\HttpFoundation\Exception\Parser\InvalidUrlException;
use Symfony\Component\HttpFoundation\Exception\Parser\MissingSchemeException;

class UrlParserTest extends TestCase
{
    public function testInvalidDsn()
    {
        $this->expectException(InvalidUrlException::class);
        $this->expectExceptionMessage('The URL is invalid.');

        UrlParser::parse('/search:2019');
    }

    public function testMissingScheme()
    {
        $this->expectException(MissingSchemeException::class);
        $this->expectExceptionMessage('The URL must contain a scheme.');

        UrlParser::parse('://example.com');
    }

    public function testReturnsFullParsedDsn()
    {
        $parsedDsn = UrlParser::parse('http://user:pass@localhost:8080/path?query=1#fragment');

        $this->assertSame('http', $parsedDsn->scheme);
        $this->assertSame('user', $parsedDsn->user);
        $this->assertSame('pass', $parsedDsn->password);
        $this->assertSame('localhost', $parsedDsn->host);
        $this->assertSame(8080, $parsedDsn->port);
        $this->assertSame('/path', $parsedDsn->path);
        $this->assertSame('query=1', $parsedDsn->query);
        $this->assertSame('fragment', $parsedDsn->fragment);
    }

    public function testItDecodesByDefault()
    {
        $parsedDsn = UrlParser::parse('http://user%20one:p%40ss@localhost:8080/path?query=1#fragment');

        $this->assertSame('user one', $parsedDsn->user);
        $this->assertSame('p@ss', $parsedDsn->password);
    }

    public function testDisableDecoding()
    {
        $parsedDsn = UrlParser::parse('http://user%20one:p%40ss@localhost:8080/path?query=1#fragment', decodeAuth: false);

        $this->assertSame('user%20one', $parsedDsn->user);
        $this->assertSame('p%40ss', $parsedDsn->password);
    }

    public function testEmptyUserAndPasswordAreSetToNull()
    {
        $parsedDsn = UrlParser::parse('http://@localhost:8080/path?query=1#fragment');

        $this->assertNull($parsedDsn->user);
        $this->assertNull($parsedDsn->password);
    }
}
