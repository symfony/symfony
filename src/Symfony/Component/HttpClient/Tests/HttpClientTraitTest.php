<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClientTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HttpClientTraitTest extends TestCase
{
    use HttpClientTrait;

    private const RFC3986_BASE = 'http://a/b/c/d;p?q';

    /**
     * @dataProvider providePrepareRequestUrl
     */
    public function testPrepareRequestUrl($expected, $url, $query = [])
    {
        $defaults = [
            'base_uri' => 'http://example.com?c=c',
            'query' => ['a' => 1, 'b' => 'b'],
        ];
        [, $defaults] = self::prepareRequest(null, null, $defaults);

        [$url] = self::prepareRequest(null, $url, ['query' => $query], $defaults);
        $this->assertSame($expected, implode('', $url));
    }

    public function providePrepareRequestUrl()
    {
        yield ['http://example.com/', 'http://example.com/'];
        yield ['http://example.com/?a=1&b=b', '.'];
        yield ['http://example.com/?a=2&b=b', '.?a=2'];
        yield ['http://example.com/?a=3&b=b', '.', ['a' => 3]];
        yield ['http://example.com/?a=3&b=b', '.?a=0', ['a' => 3]];
    }

    /**
     * @dataProvider provideResolveUrl
     */
    public function testResolveUrl($base, $url, $expected)
    {
        $this->assertSame($expected, implode('', self::resolveUrl(self::parseUrl($url), self::parseUrl($base))));
    }

    /**
     * From https://github.com/guzzle/psr7/blob/master/tests/UriResoverTest.php.
     */
    public function provideResolveUrl()
    {
        return [
            [self::RFC3986_BASE, 'http:h',        'http:h'],
            [self::RFC3986_BASE, 'g',             'http://a/b/c/g'],
            [self::RFC3986_BASE, './g',           'http://a/b/c/g'],
            [self::RFC3986_BASE, 'g/',            'http://a/b/c/g/'],
            [self::RFC3986_BASE, '/g',            'http://a/g'],
            [self::RFC3986_BASE, '//g',           'http://g/'],
            [self::RFC3986_BASE, '?y',            'http://a/b/c/d;p?y'],
            [self::RFC3986_BASE, 'g?y',           'http://a/b/c/g?y'],
            [self::RFC3986_BASE, '#s',            'http://a/b/c/d;p?q#s'],
            [self::RFC3986_BASE, 'g#s',           'http://a/b/c/g#s'],
            [self::RFC3986_BASE, 'g?y#s',         'http://a/b/c/g?y#s'],
            [self::RFC3986_BASE, ';x',            'http://a/b/c/;x'],
            [self::RFC3986_BASE, 'g;x',           'http://a/b/c/g;x'],
            [self::RFC3986_BASE, 'g;x?y#s',       'http://a/b/c/g;x?y#s'],
            [self::RFC3986_BASE, '',              self::RFC3986_BASE],
            [self::RFC3986_BASE, '.',             'http://a/b/c/'],
            [self::RFC3986_BASE, './',            'http://a/b/c/'],
            [self::RFC3986_BASE, '..',            'http://a/b/'],
            [self::RFC3986_BASE, '../',           'http://a/b/'],
            [self::RFC3986_BASE, '../g',          'http://a/b/g'],
            [self::RFC3986_BASE, '../..',         'http://a/'],
            [self::RFC3986_BASE, '../../',        'http://a/'],
            [self::RFC3986_BASE, '../../g',       'http://a/g'],
            [self::RFC3986_BASE, '../../../g',    'http://a/g'],
            [self::RFC3986_BASE, '../../../../g', 'http://a/g'],
            [self::RFC3986_BASE, '/./g',          'http://a/g'],
            [self::RFC3986_BASE, '/../g',         'http://a/g'],
            [self::RFC3986_BASE, 'g.',            'http://a/b/c/g.'],
            [self::RFC3986_BASE, '.g',            'http://a/b/c/.g'],
            [self::RFC3986_BASE, 'g..',           'http://a/b/c/g..'],
            [self::RFC3986_BASE, '..g',           'http://a/b/c/..g'],
            [self::RFC3986_BASE, './../g',        'http://a/b/g'],
            [self::RFC3986_BASE, 'foo////g',      'http://a/b/c/foo////g'],
            [self::RFC3986_BASE, './g/.',         'http://a/b/c/g/'],
            [self::RFC3986_BASE, 'g/./h',         'http://a/b/c/g/h'],
            [self::RFC3986_BASE, 'g/../h',        'http://a/b/c/h'],
            [self::RFC3986_BASE, 'g;x=1/./y',     'http://a/b/c/g;x=1/y'],
            [self::RFC3986_BASE, 'g;x=1/../y',    'http://a/b/c/y'],
            // dot-segments in the query or fragment
            [self::RFC3986_BASE, 'g?y/./x',       'http://a/b/c/g?y/./x'],
            [self::RFC3986_BASE, 'g?y/../x',      'http://a/b/c/g?y/../x'],
            [self::RFC3986_BASE, 'g#s/./x',       'http://a/b/c/g#s/./x'],
            [self::RFC3986_BASE, 'g#s/../x',      'http://a/b/c/g#s/../x'],
            [self::RFC3986_BASE, 'g#s/../x',      'http://a/b/c/g#s/../x'],
            [self::RFC3986_BASE, '?y#s',          'http://a/b/c/d;p?y#s'],
            // base with fragment
            ['http://a/b/c?q#s', '?y',            'http://a/b/c?y'],
            // base with user info
            ['http://u@a/b/c/d;p?q', '.',         'http://u@a/b/c/'],
            ['http://u:p@a/b/c/d;p?q', '.',       'http://u:p@a/b/c/'],
            // path ending with slash or no slash at all
            ['http://a/b/c/d/',  'e',             'http://a/b/c/d/e'],
            ['http:no-slash',     'e',            'http:e'],
            // falsey relative parts
            [self::RFC3986_BASE, '//0',           'http://0/'],
            [self::RFC3986_BASE, '0',             'http://a/b/c/0'],
            [self::RFC3986_BASE, '?0',            'http://a/b/c/d;p?0'],
            [self::RFC3986_BASE, '#0',            'http://a/b/c/d;p?q#0'],
        ];
    }

    /**
     * @dataProvider provideParseUrl
     */
    public function testParseUrl($expected, $url, $query = [])
    {
        $expected = array_combine(['scheme', 'authority', 'path', 'query', 'fragment'], $expected);

        $this->assertSame($expected, self::parseUrl($url, $query));
    }

    public function provideParseUrl()
    {
        yield [['http:', '//example.com', null, null, null], 'http://Example.coM:80'];
        yield [['https:', '//xn--dj-kia8a.example.com:8000', '/', null, null], 'https://DÉjà.Example.com:8000/'];
        yield [[null, null, '/f%20o.o', '?a=b', '#c'], '/f o%2Eo?a=b#c'];
        yield [[null, '//a:b@foo', '/bar', null, null], '//a:b@foo/bar'];
        yield [['http:', null, null, null, null], 'http:'];
        yield [['http:', null, 'bar', null, null], 'http:bar'];
        yield [[null, null, 'bar', '?a=1&c=c', null], 'bar?a=a&b=b', ['b' => null, 'c' => 'c', 'a' => 1]];
        yield [[null, null, 'bar', '?a=b+c&b=b', null], 'bar?a=b+c', ['b' => 'b']];
        yield [[null, null, 'bar', '?a=b%2B%20c', null], 'bar?a=b+c', ['a' => 'b+ c']];
        yield [[null, null, 'bar', '?a%5Bb%5D=c', null], 'bar', ['a' => ['b' => 'c']]];
        yield [[null, null, 'bar', '?a%5Bb%5Bc%5D=d', null], 'bar?a[b[c]=d', []];
        yield [[null, null, 'bar', '?a%5Bb%5D%5Bc%5D=dd', null], 'bar?a[b][c]=d&e[f]=g', ['a' => ['b' => ['c' => 'dd']], 'e[f]' => null]];
        yield [[null, null, 'bar', '?a=b&a%5Bb%20c%5D=d&e%3Df=%E2%9C%93', null], 'bar?a=b', ['a' => ['b c' => 'd'], 'e=f' => '✓']];
    }

    /**
     * @dataProvider provideRemoveDotSegments
     */
    public function testRemoveDotSegments($expected, $url)
    {
        $this->assertSame($expected, self::removeDotSegments($url));
    }

    public function provideRemoveDotSegments()
    {
        yield ['', ''];
        yield ['', '.'];
        yield ['', '..'];
        yield ['a', './a'];
        yield ['a', '../a'];
        yield ['/a/b', '/a/./b'];
        yield ['/b/', '/a/../b/.'];
        yield ['/a//b/', '/a///../b/.'];
        yield ['/a/', '/a/b/..'];
        yield ['/a///b', '/a///b'];
    }

    public function testAuthBearerOption()
    {
        [, $options] = self::prepareRequest('POST', 'http://example.com', ['auth_bearer' => 'foobar'], HttpClientInterface::OPTIONS_DEFAULTS);
        $this->assertSame('Bearer foobar', $options['headers']['authorization'][0]);
        $this->assertSame('authorization: Bearer foobar', $options['request_headers'][0]);
    }

    /**
     * @expectedException \Symfony\Component\HttpClient\Exception\InvalidArgumentException
     * @expectedExceptionMessage Option "auth_bearer" must be a string containing only characters from the base 64 alphabet, object given.
     */
    public function testInvalidAuthBearerOption()
    {
        self::prepareRequest('POST', 'http://example.com', ['auth_bearer' => new \stdClass()], HttpClientInterface::OPTIONS_DEFAULTS);
    }

    /**
     * @expectedException \Symfony\Component\HttpClient\Exception\InvalidArgumentException
     * @expectedExceptionMessage Option "auth_bearer" must be a string containing only characters from the base 64 alphabet, invalid string given.
     */
    public function testInvalidAuthBearerValue()
    {
        self::prepareRequest('POST', 'http://example.com', ['auth_bearer' => "a\nb"], HttpClientInterface::OPTIONS_DEFAULTS);
    }

    /**
     * @expectedException \Symfony\Component\HttpClient\Exception\InvalidArgumentException
     * @expectedExceptionMessage Define either the "auth_basic" or the "auth_bearer" option, setting both is not supported.
     */
    public function testSetAuthBasicAndBearerOptions()
    {
        self::prepareRequest('POST', 'http://example.com', ['auth_bearer' => 'foo', 'auth_basic' => 'foo:bar'], HttpClientInterface::OPTIONS_DEFAULTS);
    }

    /**
     * @expectedException \Symfony\Component\HttpClient\Exception\InvalidArgumentException
     * @expectedExceptionMessage Define either the "json" or the "body" option, setting both is not supported
     */
    public function testSetJSONAndBodyOptions()
    {
        self::prepareRequest('POST', 'http://example.com', ['json' => ['foo' => 'bar'], 'body' => '<html/>'], HttpClientInterface::OPTIONS_DEFAULTS);
    }

    public function providePrepareAuthBasic()
    {
        yield ['foo:bar', 'Zm9vOmJhcg=='];
        yield [['foo', 'bar'], 'Zm9vOmJhcg=='];
        yield ['foo', 'Zm9v'];
        yield [['foo'], 'Zm9v'];
    }

    /**
     * @dataProvider providePrepareAuthBasic
     */
    public function testPrepareAuthBasic($arg, $result)
    {
        [, $options] = $this->prepareRequest('POST', 'http://example.com', ['auth_basic' => $arg], HttpClientInterface::OPTIONS_DEFAULTS);
        $this->assertSame('Basic '.$result, $options['headers']['authorization'][0]);
    }

    public function provideFingerprints()
    {
        foreach (['md5', 'sha1', 'sha256'] as $algo) {
            $hash = hash($algo, $algo);
            yield [$hash, [$algo => $hash]];
        }

        yield ['AAAA:BBBB:CCCC:DDDD:EEEE:FFFF:GGGG:HHHH:IIII:JJJJ:KKKK', ['pin-sha256' => ['AAAABBBBCCCCDDDDEEEEFFFFGGGGHHHHIIIIJJJJKKKK']]];
    }

    /**
     * @dataProvider provideFingerprints
     */
    public function testNormalizePeerFingerprint($fingerprint, $expected)
    {
        self::assertSame($expected, $this->normalizePeerFingerprint($fingerprint));
    }

    /**
     * @expectedException \Symfony\Component\HttpClient\Exception\InvalidArgumentException
     * @expectedExceptionMessage Cannot auto-detect fingerprint algorithm for "foo".
     */
    public function testNormalizePeerFingerprintException()
    {
        $this->normalizePeerFingerprint('foo');
    }

    /**
     * @expectedException \Symfony\Component\HttpClient\Exception\InvalidArgumentException
     * @expectedExceptionMessage Option "peer_fingerprint" must be string or array, object given.
     */
    public function testNormalizePeerFingerprintTypeException()
    {
        $fingerprint = new \stdClass();

        $this->normalizePeerFingerprint($fingerprint);
    }
}
