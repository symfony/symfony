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
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\HttpClientTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HttpClientTraitTest extends TestCase
{
    use HttpClientTrait;

    private const RFC3986_BASE = 'http://a/b/c/d;p?q';

    /**
     * @dataProvider providePrepareRequestUrl
     */
    public function testPrepareRequestUrl(string $expected, string $url, array $query = [])
    {
        $defaults = [
            'base_uri' => 'http://example.com?c=c',
            'query' => ['a' => 1, 'b' => 'b'],
        ];
        [, $defaults] = self::prepareRequest(null, null, $defaults);

        [$url] = self::prepareRequest(null, $url, ['query' => $query], $defaults);
        $this->assertSame($expected, implode('', $url));
    }

    public static function providePrepareRequestUrl(): iterable
    {
        yield ['http://example.com/', 'http://example.com/'];
        yield ['http://example.com/?a=1&b=b', '.'];
        yield ['http://example.com/?a=2&b=b', '.?a=2'];
        yield ['http://example.com/?a=3&b=b', '.', ['a' => 3]];
        yield ['http://example.com/?a=3&b=b', '.?a=0', ['a' => 3]];
        yield ['http://example.com/', 'http://example.com/', ['a' => null]];
        yield ['http://example.com/?b=', 'http://example.com/', ['b' => '']];
        yield ['http://example.com/?b=', 'http://example.com/', ['a' => null, 'b' => '']];
    }

    public function testPrepareRequestWithBodyIsArray()
    {
        $defaults = [
            'base_uri' => 'http://example.com?c=c',
            'query' => ['a' => 1, 'b' => 'b'],
            'body' => [],
        ];
        [, $defaults] = self::prepareRequest(null, null, $defaults);

        [,$options] = self::prepareRequest(null, 'http://example.com', [
            'body' => [1, 2],
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8',
            ],
        ], $defaults);

        $this->assertContains('Content-Type: application/x-www-form-urlencoded; charset=utf-8', $options['headers']);
    }

    public function testNormalizeBodyMultipart()
    {
        $file = fopen('php://memory', 'r+');
        stream_context_set_option($file, ['http' => [
            'filename' => 'test.txt',
            'content_type' => 'text/plain',
        ]]);
        fwrite($file, 'foobarbaz');
        rewind($file);

        $headers = [
            'content-type' => ['Content-Type: multipart/form-data; boundary=ABCDEF'],
        ];
        $body = [
            'foo[]' => 'bar',
            'bar' => [
                $file,
            ],
        ];

        $body = self::normalizeBody($body, $headers);

        $result = '';
        while ('' !== $data = $body(self::$CHUNK_SIZE)) {
            $result .= $data;
        }

        $expected = <<<'EOF'
            --ABCDEF
            Content-Disposition: form-data; name="foo[]"

            bar
            --ABCDEF
            Content-Disposition: form-data; name="bar[0]"; filename="test.txt"
            Content-Type: text/plain

            foobarbaz
            --ABCDEF--

            EOF;
        $expected = str_replace("\n", "\r\n", $expected);

        $this->assertSame($expected, $result);
    }

    /**
     * @group network
     *
     * @requires extension openssl
     *
     * @dataProvider provideNormalizeBodyMultipartForwardStream
     */
    public function testNormalizeBodyMultipartForwardStream($stream)
    {
        $body = [
            'logo' => $stream,
        ];

        $headers = [];
        $body = self::normalizeBody($body, $headers);

        $result = '';
        while ('' !== $data = $body(self::$CHUNK_SIZE)) {
            $result .= $data;
        }

        $this->assertSame(1, preg_match('/^Content-Type: multipart\/form-data; boundary=(?<boundary>.+)$/', $headers['content-type'][0], $matches));
        $this->assertSame('Content-Length: 3086', $headers['content-length'][0]);
        $this->assertSame(3086, \strlen($result));

        $expected = <<<EOF
            --{$matches['boundary']}
            Content-Disposition: form-data; name="logo"; filename="1f44d.png"
            Content-Type: image/png

            %A
            --{$matches['boundary']}--

            EOF;
        $expected = str_replace("\n", "\r\n", $expected);

        $this->assertStringMatchesFormat($expected, $result);
    }

    public static function provideNormalizeBodyMultipartForwardStream()
    {
        yield 'native' => [fopen('https://github.githubassets.com/images/icons/emoji/unicode/1f44d.png', 'r')];
        yield 'symfony' => [HttpClient::create()->request('GET', 'https://github.githubassets.com/images/icons/emoji/unicode/1f44d.png')->toStream()];
    }

    /**
     * @dataProvider provideResolveUrl
     */
    public function testResolveUrl(string $base, string $url, string $expected)
    {
        $this->assertSame($expected, implode('', self::resolveUrl(self::parseUrl($url), self::parseUrl($base))));
    }

    /**
     * From https://github.com/guzzle/psr7/blob/master/tests/UriResoverTest.php.
     */
    public static function provideResolveUrl(): array
    {
        return [
            [self::RFC3986_BASE, 'http:h',        'http:h'],
            [self::RFC3986_BASE, 'g',             'http://a/b/c/g'],
            [self::RFC3986_BASE, './g',           'http://a/b/c/g'],
            [self::RFC3986_BASE, 'g/',            'http://a/b/c/g/'],
            [self::RFC3986_BASE, '/g',            'http://a/g'],
            [self::RFC3986_BASE, '//g',           'http://g/'],
            [self::RFC3986_BASE, '?y',            'http://a/b/c/d;p?y'],
            [self::RFC3986_BASE, '?y={"f":1}',    'http://a/b/c/d;p?y={%22f%22:1}'],
            [self::RFC3986_BASE, 'g{oof}y',       'http://a/b/c/g{oof}y'],
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

    public function testResolveUrlWithoutScheme()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid URL: scheme is missing in "//localhost:8080". Did you forget to add "http(s)://"?');
        self::resolveUrl(self::parseUrl('localhost:8080'), null);
    }

    public function testResolveBaseUrlWitoutScheme()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid URL: scheme is missing in "//localhost:8081". Did you forget to add "http(s)://"?');
        self::resolveUrl(self::parseUrl('/foo'), self::parseUrl('localhost:8081'));
    }

    /**
     * @dataProvider provideParseUrl
     */
    public function testParseUrl(array $expected, string $url, array $query = [])
    {
        $expected = array_combine(['scheme', 'authority', 'path', 'query', 'fragment'], $expected);

        $this->assertSame($expected, self::parseUrl($url, $query));
    }

    public static function provideParseUrl(): iterable
    {
        yield [['http:', '//example.com', null, null, null], 'http://Example.coM:80'];
        yield [['https:', '//xn--dj-kia8a.example.com:8000', '/', null, null], 'https://DÉjà.Example.com:8000/'];
        yield [[null, null, '/f%20o.o', '?a=b', '#c'], '/f o%2Eo?a=b#c'];
        yield [[null, '//a:b@foo', '/bar', null, null], '//a:b@foo/bar'];
        yield [[null, '//a:b@foo', '/b{}', null, null], '//a:b@foo/b{}'];
        yield [['http:', null, null, null, null], 'http:'];
        yield [['http:', null, 'bar', null, null], 'http:bar'];
        yield [[null, null, 'bar', '?a=1&c=c', null], 'bar?a=a&b=b', ['b' => null, 'c' => 'c', 'a' => 1]];
        yield [[null, null, 'bar', '?a=b+c&b=b-._~!$%26/%27()[]*%2B%2C;%3D:@%25\\^`%7B|%7D', null], 'bar?a=b+c', ['b' => 'b-._~!$&/\'()[]*+,;=:@%\\^`{|}']];
        yield [[null, null, 'bar', '?a=b%2B%20c', null], 'bar?a=b+c', ['a' => 'b+ c']];
        yield [[null, null, 'bar', '?a[b]=c', null], 'bar', ['a' => ['b' => 'c']]];
        yield [[null, null, 'bar', '?a[b[c]=d', null], 'bar?a[b[c]=d', []];
        yield [[null, null, 'bar', '?a[b][c]=dd', null], 'bar?a[b][c]=d&e[f]=g', ['a' => ['b' => ['c' => 'dd']], 'e[f]' => null]];
        yield [[null, null, 'bar', '?a=b&a[b%20c]=d&e%3Df=%E2%9C%93', null], 'bar?a=b', ['a' => ['b c' => 'd'], 'e=f' => '✓']];
        // IDNA 2008 compliance
        yield [['https:', '//xn--fuball-cta.test', null, null, null], 'https://fußball.test'];
    }

    /**
     * @dataProvider provideRemoveDotSegments
     */
    public function testRemoveDotSegments($expected, $url)
    {
        $this->assertSame($expected, self::removeDotSegments($url));
    }

    public static function provideRemoveDotSegments()
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
        $this->assertSame(['Accept: */*', 'Authorization: Bearer foobar'], $options['headers']);
        $this->assertSame(['Authorization: Bearer foobar'], $options['normalized_headers']['authorization']);
    }

    public function testInvalidAuthBearerOption()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Option "auth_bearer" must be a string, "stdClass" given.');
        self::prepareRequest('POST', 'http://example.com', ['auth_bearer' => new \stdClass()], HttpClientInterface::OPTIONS_DEFAULTS);
    }

    public function testInvalidAuthBearerValue()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid character found in option "auth_bearer": "a\nb".');
        self::prepareRequest('POST', 'http://example.com', ['auth_bearer' => "a\nb"], HttpClientInterface::OPTIONS_DEFAULTS);
    }

    public function testSetAuthBasicAndBearerOptions()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Define either the "auth_basic" or the "auth_bearer" option, setting both is not supported.');
        self::prepareRequest('POST', 'http://example.com', ['auth_bearer' => 'foo', 'auth_basic' => 'foo:bar'], HttpClientInterface::OPTIONS_DEFAULTS);
    }

    public function testSetJSONAndBodyOptions()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Define either the "json" or the "body" option, setting both is not supported');
        self::prepareRequest('POST', 'http://example.com', ['json' => ['foo' => 'bar'], 'body' => '<html/>'], HttpClientInterface::OPTIONS_DEFAULTS);
    }

    public static function providePrepareAuthBasic()
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
        $this->assertSame('Authorization: Basic '.$result, $options['normalized_headers']['authorization'][0]);
    }

    public static function provideFingerprints()
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

    public function testNormalizePeerFingerprintException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot auto-detect fingerprint algorithm for "foo".');
        $this->normalizePeerFingerprint('foo');
    }

    public function testNormalizePeerFingerprintTypeException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Option "peer_fingerprint" must be string or array, "stdClass" given.');
        $fingerprint = new \stdClass();

        $this->normalizePeerFingerprint($fingerprint);
    }
}
