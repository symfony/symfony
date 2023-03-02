<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlSanitizer\Tests\TextSanitizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\TextSanitizer\UrlSanitizer;

class UrlSanitizerTest extends TestCase
{
    /**
     * @dataProvider provideSanitize
     */
    public function testSanitize(?string $input, ?array $allowedSchemes, ?array $allowedHosts, bool $forceHttps, bool $allowRelative, ?string $expected)
    {
        $this->assertSame($expected, UrlSanitizer::sanitize($input, $allowedSchemes, $forceHttps, $allowedHosts, $allowRelative));
    }

    public static function provideSanitize()
    {
        // Simple accepted cases
        yield [
            'input' => '',
            'allowedSchemes' => ['https'],
            'allowedHosts' => null,
            'forceHttps' => false,
            'allowRelative' => false,
            'output' => null,
        ];

        yield [
            'input' => ':invalid',
            'allowedSchemes' => ['https'],
            'allowedHosts' => null,
            'forceHttps' => false,
            'allowRelative' => false,
            'output' => null,
        ];

        yield [
            'input' => 'http://trusted.com/link.php',
            'allowedSchemes' => null,
            'allowedHosts' => null,
            'forceHttps' => false,
            'allowRelative' => false,
            'output' => 'http://trusted.com/link.php',
        ];

        yield [
            'input' => 'https://trusted.com/link.php',
            'allowedSchemes' => null,
            'allowedHosts' => null,
            'forceHttps' => false,
            'allowRelative' => false,
            'output' => 'https://trusted.com/link.php',
        ];

        yield [
            'input' => 'data:text/plain;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7',
            'allowedSchemes' => null,
            'allowedHosts' => null,
            'forceHttps' => false,
            'allowRelative' => false,
            'output' => 'data:text/plain;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7',
        ];

        yield [
            'input' => 'https://trusted.com/link.php',
            'allowedSchemes' => ['https'],
            'allowedHosts' => null,
            'forceHttps' => false,
            'allowRelative' => false,
            'output' => 'https://trusted.com/link.php',
        ];

        yield [
            'input' => 'https://trusted.com/link.php',
            'allowedSchemes' => ['https'],
            'allowedHosts' => ['trusted.com'],
            'forceHttps' => false,
            'allowRelative' => false,
            'output' => 'https://trusted.com/link.php',
        ];

        yield [
            'input' => 'http://trusted.com/link.php',
            'allowedSchemes' => ['http'],
            'allowedHosts' => ['trusted.com'],
            'forceHttps' => false,
            'allowRelative' => false,
            'output' => 'http://trusted.com/link.php',
        ];

        yield [
            'input' => 'data:text/plain;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7',
            'allowedSchemes' => ['data'],
            'allowedHosts' => null,
            'forceHttps' => false,
            'allowRelative' => false,
            'output' => 'data:text/plain;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7',
        ];

        // Simple filtered cases
        yield [
            'input' => 'ws://trusted.com/link.php',
            'allowedSchemes' => ['http'],
            'allowedHosts' => null,
            'forceHttps' => false,
            'allowRelative' => false,
            'output' => null,
        ];

        yield [
            'input' => 'http:link.php',
            'allowedSchemes' => ['http'],
            'allowedHosts' => null,
            'forceHttps' => false,
            'allowRelative' => false,
            'output' => null,
        ];

        yield [
            'input' => 'http:link.php',
            'allowedSchemes' => ['http'],
            'allowedHosts' => null,
            'forceHttps' => false,
            'allowRelative' => true,
            'output' => 'http:link.php',
        ];

        yield [
            'input' => 'ws://trusted.com/link.php',
            'allowedSchemes' => ['http'],
            'allowedHosts' => ['trusted.com'],
            'forceHttps' => false,
            'allowRelative' => false,
            'output' => null,
        ];

        yield [
            'input' => 'https://trusted.com/link.php',
            'allowedSchemes' => ['http'],
            'allowedHosts' => null,
            'forceHttps' => false,
            'allowRelative' => false,
            'output' => null,
        ];

        yield [
            'input' => 'https://untrusted.com/link.php',
            'allowedSchemes' => ['https'],
            'allowedHosts' => ['trusted.com'],
            'forceHttps' => false,
            'allowRelative' => false,
            'output' => null,
        ];

        yield [
            'input' => 'http://untrusted.com/link.php',
            'allowedSchemes' => ['http'],
            'allowedHosts' => ['trusted.com'],
            'forceHttps' => false,
            'allowRelative' => false,
            'output' => null,
        ];

        yield [
            'input' => 'data:text/plain;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7',
            'allowedSchemes' => ['http'],
            'allowedHosts' => null,
            'forceHttps' => false,
            'allowRelative' => false,
            'output' => null,
        ];

        yield [
            'input' => 'data:text/plain;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7',
            'allowedSchemes' => ['http'],
            'allowedHosts' => ['trusted.com'],
            'forceHttps' => false,
            'allowRelative' => false,
            'output' => null,
        ];

        // Allow null host (data scheme for instance)
        yield [
            'input' => 'data:text/plain;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7',
            'allowedSchemes' => ['http', 'https', 'data'],
            'allowedHosts' => ['trusted.com', null],
            'forceHttps' => false,
            'allowRelative' => false,
            'output' => 'data:text/plain;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7',
        ];

        // Force HTTPS
        yield [
            'input' => 'http://trusted.com/link.php',
            'allowedSchemes' => ['http', 'https'],
            'allowedHosts' => ['trusted.com'],
            'forceHttps' => true,
            'allowRelative' => false,
            'output' => 'https://trusted.com/link.php',
        ];

        yield [
            'input' => 'https://trusted.com/link.php',
            'allowedSchemes' => ['http', 'https'],
            'allowedHosts' => ['trusted.com'],
            'forceHttps' => true,
            'allowRelative' => false,
            'output' => 'https://trusted.com/link.php',
        ];

        yield [
            'input' => 'data:text/plain;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7',
            'allowedSchemes' => ['http', 'https', 'data'],
            'allowedHosts' => null,
            'forceHttps' => true,
            'allowRelative' => false,
            'output' => 'data:text/plain;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7',
        ];

        yield [
            'input' => 'data:text/plain;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7',
            'allowedSchemes' => ['http', 'https', 'data'],
            'allowedHosts' => ['trusted.com', null],
            'forceHttps' => true,
            'allowRelative' => false,
            'output' => 'data:text/plain;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7',
        ];

        // Domain matching
        yield [
            'input' => 'https://subdomain.trusted.com/link.php',
            'allowedSchemes' => ['http', 'https'],
            'allowedHosts' => ['trusted.com'],
            'forceHttps' => false,
            'allowRelative' => false,
            'output' => 'https://subdomain.trusted.com/link.php',
        ];

        yield [
            'input' => 'https://subdomain.trusted.com.untrusted.com/link.php',
            'allowedSchemes' => ['http', 'https'],
            'allowedHosts' => ['trusted.com'],
            'forceHttps' => false,
            'allowRelative' => false,
            'output' => null,
        ];

        yield [
            'input' => 'https://deep.subdomain.trusted.com/link.php',
            'allowedSchemes' => ['http', 'https'],
            'allowedHosts' => ['trusted.com'],
            'forceHttps' => false,
            'allowRelative' => false,
            'output' => 'https://deep.subdomain.trusted.com/link.php',
        ];

        yield [
            'input' => 'https://deep.subdomain.trusted.com.untrusted.com/link.php',
            'allowedSchemes' => ['http', 'https'],
            'allowedHosts' => ['trusted.com'],
            'forceHttps' => false,
            'allowRelative' => false,
            'output' => null,
        ];

        // Allow relative
        yield [
            'input' => '/link.php',
            'allowedSchemes' => ['http', 'https'],
            'allowedHosts' => ['trusted.com'],
            'forceHttps' => true,
            'allowRelative' => true,
            'output' => '/link.php',
        ];

        yield [
            'input' => '/link.php',
            'allowedSchemes' => ['http', 'https'],
            'allowedHosts' => ['trusted.com'],
            'forceHttps' => true,
            'allowRelative' => false,
            'output' => null,
        ];
    }

    /**
     * @dataProvider provideParse
     */
    public function testParse(string $url, ?array $expected)
    {
        $parsed = UrlSanitizer::parse($url);

        if (null === $expected) {
            $this->assertNull($parsed);
        } else {
            $this->assertIsArray($parsed);
            $this->assertArrayHasKey('scheme', $parsed);
            $this->assertArrayHasKey('host', $parsed);
            $this->assertSame($expected['scheme'], $parsed['scheme']);
            $this->assertSame($expected['host'], $parsed['host']);
        }
    }

    public static function provideParse(): iterable
    {
        $urls = [
            '' => null,

            // Simple tests
            'https://trusted.com/link.php' => ['scheme' => 'https', 'host' => 'trusted.com'],
            'https://trusted.com/link.php?query=1#foo' => ['scheme' => 'https', 'host' => 'trusted.com'],
            'https://subdomain.trusted.com/link' => ['scheme' => 'https', 'host' => 'subdomain.trusted.com'],
            '//trusted.com/link.php' => ['scheme' => null, 'host' => 'trusted.com'],
            'https:trusted.com/link.php' => ['scheme' => 'https', 'host' => null],
            'https://untrusted.com/link' => ['scheme' => 'https', 'host' => 'untrusted.com'],

            // Ensure https://bugs.php.net/bug.php?id=73192 is handled
            'https://untrusted.com:80?@trusted.com/' => ['scheme' => 'https', 'host' => 'untrusted.com'],
            'https://untrusted.com:80#@trusted.com/' => ['scheme' => 'https', 'host' => 'untrusted.com'],

            // Ensure https://medium.com/secjuice/php-ssrf-techniques-9d422cb28d51 is handled
            '0://untrusted.com;trusted.com' => null,
            '0://untrusted.com:80;trusted.com:80' => null,
            '0://untrusted.com:80,trusted.com:80' => null,

            // Data-URI
            'data:text/plain;base64,SSBsb3ZlIFBIUAo' => ['scheme' => 'data', 'host' => null],
            'data:text/plain;base64,SSBsb3ZlIFBIUAo=trusted.com' => ['scheme' => 'data', 'host' => null],
            'data:http://trusted.com' => ['scheme' => 'data', 'host' => null],
            'data://text/plain;base64,SSBsb3ZlIFBIUAo=trusted.com' => ['scheme' => 'data', 'host' => 'text'],
            'data://image/png;base64,SSBsb3ZlIFBIUAo=trusted.com' => ['scheme' => 'data', 'host' => 'image'],
            'data:google.com/plain;base64,SSBsb3ZlIFBIUAo=' => ['scheme' => 'data', 'host' => null],
            'data://google.com/plain;base64,SSBsb3ZlIFBIUAo=' => ['scheme' => 'data', 'host' => 'google.com'],

            // Inspired by https://github.com/punkave/sanitize-html/blob/master/test/test.js
            "java\0&#14;\t\r\n script:alert(\'foo\')" => null,
            'java&#0000001script:alert(\\\'foo\\\')' => ['scheme' => null, 'host' => null],
            'java&#0000000script:alert(\\\'foo\\\')' => ['scheme' => null, 'host' => null],
            'java<!-- -->script:alert(\'foo\')' => null,

            // Extracted from https://github.com/web-platform-tests/wpt/blob/master/url/resources/urltestdata.json
            "http://example	.\norg" => null,
            'http://user:pass@foo:21/bar;par?b#c' => ['scheme' => 'http', 'host' => 'foo'],
            'https://trusted.com:@untrusted.com' => ['scheme' => 'https', 'host' => 'untrusted.com'],
            'https://:@untrusted.com' => ['scheme' => 'https', 'host' => 'untrusted.com'],
            'non-special://test:@untrusted.com/x' => ['scheme' => 'non-special', 'host' => 'untrusted.com'],
            'non-special://:@untrusted.com/x' => ['scheme' => 'non-special', 'host' => 'untrusted.com'],
            'http:foo.com' => ['scheme' => 'http', 'host' => null],
            "	   :foo.com   \n" => null,
            ' foo.com  ' => ['scheme' => null, 'host' => null],
            'a:	 foo.com' => null,
            'http://f:21/ b ? d # e ' => ['scheme' => 'http', 'host' => 'f'],
            'lolscheme:x x#x x' => ['scheme' => 'lolscheme', 'host' => null],
            'http://f:/c' => ['scheme' => 'http', 'host' => 'f'],
            'http://f:0/c' => ['scheme' => 'http', 'host' => 'f'],
            'http://f:00000000000000/c' => ['scheme' => 'http', 'host' => 'f'],
            'http://f:00000000000000000000080/c' => ['scheme' => 'http', 'host' => 'f'],
            "http://f:\n/c" => null,
            '  	' => null,
            ':foo.com/' => null,
            ':foo.com\\' => ['scheme' => null, 'host' => null],
            ':' => ['scheme' => null, 'host' => null],
            ':a' => ['scheme' => null, 'host' => null],
            ':/' => null,
            ':\\' => ['scheme' => null, 'host' => null],
            ':#' => ['scheme' => null, 'host' => null],
            '#' => ['scheme' => null, 'host' => null],
            '#/' => ['scheme' => null, 'host' => null],
            '#\\' => ['scheme' => null, 'host' => null],
            '#;?' => ['scheme' => null, 'host' => null],
            '?' => ['scheme' => null, 'host' => null],
            '/' => ['scheme' => null, 'host' => null],
            ':23' => ['scheme' => null, 'host' => null],
            '/:23' => ['scheme' => null, 'host' => null],
            '::' => ['scheme' => null, 'host' => null],
            '::23' => ['scheme' => null, 'host' => null],
            'foo://' => ['scheme' => 'foo', 'host' => ''],
            'http://a:b@c:29/d' => ['scheme' => 'http', 'host' => 'c'],
            'http::@c:29' => ['scheme' => 'http', 'host' => null],
            'http://&a:foo(b]c@d:2/' => ['scheme' => 'http', 'host' => 'd'],
            'http://::@c@d:2' => null,
            'http://foo.com:b@d/' => ['scheme' => 'http', 'host' => 'd'],
            'http://foo.com/\\@' => ['scheme' => 'http', 'host' => 'foo.com'],
            'http:\\foo.com\\' => ['scheme' => 'http', 'host' => null],
            'http:\\a\\b:c\\d@foo.com\\' => ['scheme' => 'http', 'host' => null],
            'foo:/' => ['scheme' => 'foo', 'host' => null],
            'foo:/bar.com/' => ['scheme' => 'foo', 'host' => null],
            'foo://///////' => ['scheme' => 'foo', 'host' => ''],
            'foo://///////bar.com/' => ['scheme' => 'foo', 'host' => ''],
            'foo:////://///' => ['scheme' => 'foo', 'host' => ''],
            'c:/foo' => ['scheme' => 'c', 'host' => null],
            '//foo/bar' => ['scheme' => null, 'host' => 'foo'],
            'http://foo/path;a??e#f#g' => ['scheme' => 'http', 'host' => 'foo'],
            'http://foo/abcd?efgh?ijkl' => ['scheme' => 'http', 'host' => 'foo'],
            'http://foo/abcd#foo?bar' => ['scheme' => 'http', 'host' => 'foo'],
            '[61:24:74]:98' => null,
            'http:[61:27]/:foo' => ['scheme' => 'http', 'host' => null],
            'http://[2001::1]' => ['scheme' => 'http', 'host' => '[2001::1]'],
            'http://[::127.0.0.1]' => ['scheme' => 'http', 'host' => '[::127.0.0.1]'],
            'http://[0:0:0:0:0:0:13.1.68.3]' => ['scheme' => 'http', 'host' => '[0:0:0:0:0:0:13.1.68.3]'],
            'http://[2001::1]:80' => ['scheme' => 'http', 'host' => '[2001::1]'],
            'http:/example.com/' => ['scheme' => 'http', 'host' => null],
            'ftp:/example.com/' => ['scheme' => 'ftp', 'host' => null],
            'https:/example.com/' => ['scheme' => 'https', 'host' => null],
            'madeupscheme:/example.com/' => ['scheme' => 'madeupscheme', 'host' => null],
            'file:/example.com/' => ['scheme' => 'file', 'host' => null],
            'ftps:/example.com/' => ['scheme' => 'ftps', 'host' => null],
            'gopher:/example.com/' => ['scheme' => 'gopher', 'host' => null],
            'ws:/example.com/' => ['scheme' => 'ws', 'host' => null],
            'wss:/example.com/' => ['scheme' => 'wss', 'host' => null],
            'data:/example.com/' => ['scheme' => 'data', 'host' => null],
            'javascript:/example.com/' => ['scheme' => 'javascript', 'host' => null],
            'mailto:/example.com/' => ['scheme' => 'mailto', 'host' => null],
            'http:example.com/' => ['scheme' => 'http', 'host' => null],
            'ftp:example.com/' => ['scheme' => 'ftp', 'host' => null],
            'https:example.com/' => ['scheme' => 'https', 'host' => null],
            'madeupscheme:example.com/' => ['scheme' => 'madeupscheme', 'host' => null],
            'ftps:example.com/' => ['scheme' => 'ftps', 'host' => null],
            'gopher:example.com/' => ['scheme' => 'gopher', 'host' => null],
            'ws:example.com/' => ['scheme' => 'ws', 'host' => null],
            'wss:example.com/' => ['scheme' => 'wss', 'host' => null],
            'data:example.com/' => ['scheme' => 'data', 'host' => null],
            'javascript:example.com/' => ['scheme' => 'javascript', 'host' => null],
            'mailto:example.com/' => ['scheme' => 'mailto', 'host' => null],
            '/a/b/c' => ['scheme' => null, 'host' => null],
            '/a/ /c' => ['scheme' => null, 'host' => null],
            '/a%2fc' => ['scheme' => null, 'host' => null],
            '/a/%2f/c' => ['scheme' => null, 'host' => null],
            '#β' => ['scheme' => null, 'host' => null],
            'data:text/html,test#test' => ['scheme' => 'data', 'host' => null],
            'tel:1234567890' => ['scheme' => 'tel', 'host' => null],
            'ssh://example.com/foo/bar.git' => ['scheme' => 'ssh', 'host' => 'example.com'],
            "file:c:\foo\bar.html" => null,
            '  File:c|////foo\\bar.html' => null,
            'C|/foo/bar' => ['scheme' => null, 'host' => null],
            "/C|\foo\bar" => null,
            '//C|/foo/bar' => null,
            '//server/file' => ['scheme' => null, 'host' => 'server'],
            "\\server\file" => null,
            '/\\server/file' => ['scheme' => null, 'host' => null],
            'file:///foo/bar.txt' => ['scheme' => 'file', 'host' => ''],
            'file:///home/me' => ['scheme' => 'file', 'host' => ''],
            '//' => ['scheme' => null, 'host' => ''],
            '///' => ['scheme' => null, 'host' => ''],
            '///test' => ['scheme' => null, 'host' => ''],
            'file://test' => ['scheme' => 'file', 'host' => 'test'],
            'file://localhost' => ['scheme' => 'file', 'host' => 'localhost'],
            'file://localhost/' => ['scheme' => 'file', 'host' => 'localhost'],
            'file://localhost/test' => ['scheme' => 'file', 'host' => 'localhost'],
            'test' => ['scheme' => null, 'host' => null],
            'file:test' => ['scheme' => 'file', 'host' => null],
            'http://example.com/././foo' => ['scheme' => 'http', 'host' => 'example.com'],
            'http://example.com/./.foo' => ['scheme' => 'http', 'host' => 'example.com'],
            'http://example.com/foo/.' => ['scheme' => 'http', 'host' => 'example.com'],
            'http://example.com/foo/./' => ['scheme' => 'http', 'host' => 'example.com'],
            'http://example.com/foo/bar/..' => ['scheme' => 'http', 'host' => 'example.com'],
            'http://example.com/foo/bar/../' => ['scheme' => 'http', 'host' => 'example.com'],
            'http://example.com/foo/..bar' => ['scheme' => 'http', 'host' => 'example.com'],
            'http://example.com/foo/bar/../ton' => ['scheme' => 'http', 'host' => 'example.com'],
            'http://example.com/foo/bar/../ton/../../a' => ['scheme' => 'http', 'host' => 'example.com'],
            'http://example.com/foo/../../..' => ['scheme' => 'http', 'host' => 'example.com'],
            'http://example.com/foo/../../../ton' => ['scheme' => 'http', 'host' => 'example.com'],
            'http://example.com/foo/%2e' => ['scheme' => 'http', 'host' => 'example.com'],
            'http://example.com/foo/%2e%2' => ['scheme' => 'http', 'host' => 'example.com'],
            'http://example.com/foo/%2e./%2e%2e/.%2e/%2e.bar' => ['scheme' => 'http', 'host' => 'example.com'],
            'http://example.com////../..' => ['scheme' => 'http', 'host' => 'example.com'],
            'http://example.com/foo/bar//../..' => ['scheme' => 'http', 'host' => 'example.com'],
            'http://example.com/foo/bar//..' => ['scheme' => 'http', 'host' => 'example.com'],
            'http://example.com/foo' => ['scheme' => 'http', 'host' => 'example.com'],
            'http://example.com/%20foo' => ['scheme' => 'http', 'host' => 'example.com'],
            'http://example.com/foo%' => ['scheme' => 'http', 'host' => 'example.com'],
            'http://example.com/foo%2' => ['scheme' => 'http', 'host' => 'example.com'],
            'http://example.com/foo%2zbar' => ['scheme' => 'http', 'host' => 'example.com'],
            'http://example.com/foo%2Â©zbar' => ['scheme' => 'http', 'host' => 'example.com'],
            'http://example.com/foo%41%7a' => ['scheme' => 'http', 'host' => 'example.com'],
            "http://example.com/foo	\u{0091}%91" => null,
            'http://example.com/foo%00%51' => ['scheme' => 'http', 'host' => 'example.com'],
            'http://example.com/(%28:%3A%29)' => ['scheme' => 'http', 'host' => 'example.com'],
            'http://example.com/%3A%3a%3C%3c' => ['scheme' => 'http', 'host' => 'example.com'],
            'http://example.com/foo	bar' => null,
            'http://example.com\\foo\\bar' => null,
            'http://example.com/%7Ffp3%3Eju%3Dduvgw%3Dd' => ['scheme' => 'http', 'host' => 'example.com'],
            'http://example.com/@asdf%40' => ['scheme' => 'http', 'host' => 'example.com'],
            'http://example.com/你好你好' => ['scheme' => 'http', 'host' => 'example.com'],
            'http://example.com/‥/foo' => ['scheme' => 'http', 'host' => 'example.com'],
            "http://example.com/\u{feff}/foo" => ['scheme' => 'http', 'host' => 'example.com'],
            "http://example.com\u{002f}\u{202e}\u{002f}\u{0066}\u{006f}\u{006f}\u{002f}\u{202d}\u{002f}\u{0062}\u{0061}\u{0072}\u{0027}\u{0020}" => ['scheme' => 'http', 'host' => 'example.com'],
            'http://www.google.com/foo?bar=baz#' => ['scheme' => 'http', 'host' => 'www.google.com'],
            'http://www.google.com/foo?bar=baz# »' => ['scheme' => 'http', 'host' => 'www.google.com'],
            'data:test# »' => ['scheme' => 'data', 'host' => null],
            'http://www.google.com' => ['scheme' => 'http', 'host' => 'www.google.com'],
            'http://192.0x00A80001' => ['scheme' => 'http', 'host' => '192.0x00A80001'],
            'http://www/foo%2Ehtml' => ['scheme' => 'http', 'host' => 'www'],
            'http://www/foo/%2E/html' => ['scheme' => 'http', 'host' => 'www'],
            'http://%25DOMAIN:foobar@foodomain.com/' => ['scheme' => 'http', 'host' => 'foodomain.com'],
            "http:\\www.google.com\foo" => null,
            'http://foo:80/' => ['scheme' => 'http', 'host' => 'foo'],
            'http://foo:81/' => ['scheme' => 'http', 'host' => 'foo'],
            'httpa://foo:80/' => ['scheme' => 'httpa', 'host' => 'foo'],
            'https://foo:443/' => ['scheme' => 'https', 'host' => 'foo'],
            'https://foo:80/' => ['scheme' => 'https', 'host' => 'foo'],
            'ftp://foo:21/' => ['scheme' => 'ftp', 'host' => 'foo'],
            'ftp://foo:80/' => ['scheme' => 'ftp', 'host' => 'foo'],
            'gopher://foo:70/' => ['scheme' => 'gopher', 'host' => 'foo'],
            'gopher://foo:443/' => ['scheme' => 'gopher', 'host' => 'foo'],
            'ws://foo:80/' => ['scheme' => 'ws', 'host' => 'foo'],
            'ws://foo:81/' => ['scheme' => 'ws', 'host' => 'foo'],
            'ws://foo:443/' => ['scheme' => 'ws', 'host' => 'foo'],
            'ws://foo:815/' => ['scheme' => 'ws', 'host' => 'foo'],
            'wss://foo:80/' => ['scheme' => 'wss', 'host' => 'foo'],
            'wss://foo:81/' => ['scheme' => 'wss', 'host' => 'foo'],
            'wss://foo:443/' => ['scheme' => 'wss', 'host' => 'foo'],
            'wss://foo:815/' => ['scheme' => 'wss', 'host' => 'foo'],
            'http:@www.example.com' => ['scheme' => 'http', 'host' => null],
            'http:/@www.example.com' => ['scheme' => 'http', 'host' => null],
            'http://@www.example.com' => ['scheme' => 'http', 'host' => 'www.example.com'],
            'http:a:b@www.example.com' => ['scheme' => 'http', 'host' => null],
            'http:/a:b@www.example.com' => ['scheme' => 'http', 'host' => null],
            'http://a:b@www.example.com' => ['scheme' => 'http', 'host' => 'www.example.com'],
            'http://@pple.com' => ['scheme' => 'http', 'host' => 'pple.com'],
            'http::b@www.example.com' => ['scheme' => 'http', 'host' => null],
            'http:/:b@www.example.com' => ['scheme' => 'http', 'host' => null],
            'http://:b@www.example.com' => ['scheme' => 'http', 'host' => 'www.example.com'],
            'http:a:@www.example.com' => ['scheme' => 'http', 'host' => null],
            'http:/a:@www.example.com' => ['scheme' => 'http', 'host' => null],
            'http://a:@www.example.com' => ['scheme' => 'http', 'host' => 'www.example.com'],
            'http://www.@pple.com' => ['scheme' => 'http', 'host' => 'pple.com'],
            'http://:@www.example.com' => ['scheme' => 'http', 'host' => 'www.example.com'],
            '/test.txt' => ['scheme' => null, 'host' => null],
            '.' => ['scheme' => null, 'host' => null],
            '..' => ['scheme' => null, 'host' => null],
            'test.txt' => ['scheme' => null, 'host' => null],
            './test.txt' => ['scheme' => null, 'host' => null],
            '../test.txt' => ['scheme' => null, 'host' => null],
            '../aaa/test.txt' => ['scheme' => null, 'host' => null],
            '../../test.txt' => ['scheme' => null, 'host' => null],
            '中/test.txt' => ['scheme' => null, 'host' => null],
            'http://www.example2.com' => ['scheme' => 'http', 'host' => 'www.example2.com'],
            '//www.example2.com' => ['scheme' => null, 'host' => 'www.example2.com'],
            'file:...' => ['scheme' => 'file', 'host' => null],
            'file:..' => ['scheme' => 'file', 'host' => null],
            'file:a' => ['scheme' => 'file', 'host' => null],
            'http://ExAmPlE.CoM' => ['scheme' => 'http', 'host' => 'ExAmPlE.CoM'],
            "http://GOO\u{200b}\u{2060}\u{feff}goo.com" => ['scheme' => 'http', 'host' => "GOO\u{200b}\u{2060}\u{feff}goo.com"],
            'http://www.foo。bar.com' => ['scheme' => 'http', 'host' => 'www.foo。bar.com'],
            'https://x/�?�#�' => ['scheme' => 'https', 'host' => 'x'],
            'http://Ｇｏ.com' => ['scheme' => 'http', 'host' => 'Ｇｏ.com'],
            'http://你好你好' => ['scheme' => 'http', 'host' => '你好你好'],
            'https://faß.ExAmPlE/' => ['scheme' => 'https', 'host' => 'faß.ExAmPlE'],
            'sc://faß.ExAmPlE/' => ['scheme' => 'sc', 'host' => 'faß.ExAmPlE'],
            'http://%30%78%63%30%2e%30%32%35%30.01' => ['scheme' => 'http', 'host' => '%30%78%63%30%2e%30%32%35%30.01'],
            'http://%30%78%63%30%2e%30%32%35%30.01%2e' => ['scheme' => 'http', 'host' => '%30%78%63%30%2e%30%32%35%30.01%2e'],
            'http://０Ｘｃ０．０２５０．０１' => ['scheme' => 'http', 'host' => '０Ｘｃ０．０２５０．０１'],
            'http://./' => ['scheme' => 'http', 'host' => '.'],
            'http://../' => ['scheme' => 'http', 'host' => '..'],
            'http://0..0x300/' => ['scheme' => 'http', 'host' => '0..0x300'],
            'http://foo:💩@example.com/bar' => ['scheme' => 'http', 'host' => 'example.com'],
            '#x' => ['scheme' => null, 'host' => null],
            'https://@test@test@example:800/' => null,
            'https://@@@example' => null,
            'http://`{}:`{}@h/`{}?`{}' => ['scheme' => 'http', 'host' => 'h'],
            'http://host/?\'' => ['scheme' => 'http', 'host' => 'host'],
            'notspecial://host/?\'' => ['scheme' => 'notspecial', 'host' => 'host'],
            '/some/path' => ['scheme' => null, 'host' => null],
            'i' => ['scheme' => null, 'host' => null],
            '../i' => ['scheme' => null, 'host' => null],
            '/i' => ['scheme' => null, 'host' => null],
            '?i' => ['scheme' => null, 'host' => null],
            '#i' => ['scheme' => null, 'host' => null],
            'about:/../' => ['scheme' => 'about', 'host' => null],
            'data:/../' => ['scheme' => 'data', 'host' => null],
            'javascript:/../' => ['scheme' => 'javascript', 'host' => null],
            'mailto:/../' => ['scheme' => 'mailto', 'host' => null],
            'sc://ñ.test/' => ['scheme' => 'sc', 'host' => 'ñ.test'],
            'sc://!"$&\'()*+,-.;<=>^_`{|}~/' => null,
            'sc://%/' => null,
            'x' => ['scheme' => null, 'host' => null],
            'sc:\\../' => ['scheme' => 'sc', 'host' => null],
            'sc::a@example.net' => ['scheme' => 'sc', 'host' => null],
            'wow:%NBD' => ['scheme' => 'wow', 'host' => null],
            'wow:%1G' => ['scheme' => 'wow', 'host' => null],
            'ftp://%e2%98%83' => ['scheme' => 'ftp', 'host' => '%e2%98%83'],
            'https://%e2%98%83' => ['scheme' => 'https', 'host' => '%e2%98%83'],
            'http://127.0.0.1:10100/relative_import.html' => ['scheme' => 'http', 'host' => '127.0.0.1'],
            'http://facebook.com/?foo=%7B%22abc%22' => ['scheme' => 'http', 'host' => 'facebook.com'],
            'https://localhost:3000/jqueryui@1.2.3' => ['scheme' => 'https', 'host' => 'localhost'],
            '?a=b&c=d' => ['scheme' => null, 'host' => null],
            '??a=b&c=d' => ['scheme' => null, 'host' => null],
            'http:' => ['scheme' => 'http', 'host' => null],
            'sc:' => ['scheme' => 'sc', 'host' => null],
            'http://foo.bar/baz?qux#fobar' => ['scheme' => 'http', 'host' => 'foo.bar'],
            'http://foo.bar/baz?qux#foo"bar' => ['scheme' => 'http', 'host' => 'foo.bar'],
            'http://foo.bar/baz?qux#foo<bar' => ['scheme' => 'http', 'host' => 'foo.bar'],
            'http://foo.bar/baz?qux#foo>bar' => ['scheme' => 'http', 'host' => 'foo.bar'],
            'http://foo.bar/baz?qux#foo`bar' => ['scheme' => 'http', 'host' => 'foo.bar'],
            'http://192.168.257' => ['scheme' => 'http', 'host' => '192.168.257'],
            'http://192.168.257.com' => ['scheme' => 'http', 'host' => '192.168.257.com'],
            'http://256' => ['scheme' => 'http', 'host' => '256'],
            'http://256.com' => ['scheme' => 'http', 'host' => '256.com'],
            'http://999999999' => ['scheme' => 'http', 'host' => '999999999'],
            'http://999999999.com' => ['scheme' => 'http', 'host' => '999999999.com'],
            'http://10000000000.com' => ['scheme' => 'http', 'host' => '10000000000.com'],
            'http://4294967295' => ['scheme' => 'http', 'host' => '4294967295'],
            'http://0xffffffff' => ['scheme' => 'http', 'host' => '0xffffffff'],
            'http://256.256.256.256.256' => ['scheme' => 'http', 'host' => '256.256.256.256.256'],
            'https://0x.0x.0' => ['scheme' => 'https', 'host' => '0x.0x.0'],
            'file:///C%3A/' => ['scheme' => 'file', 'host' => ''],
            'file:///C%7C/' => ['scheme' => 'file', 'host' => ''],
            'pix/submit.gif' => ['scheme' => null, 'host' => null],
            '//d:' => ['scheme' => null, 'host' => 'd'],
            '//d:/..' => ['scheme' => null, 'host' => 'd'],
            'file:' => ['scheme' => 'file', 'host' => null],
            '?x' => ['scheme' => null, 'host' => null],
            'file:?x' => ['scheme' => 'file', 'host' => null],
            'file:#x' => ['scheme' => 'file', 'host' => null],
            'file:\\//' => ['scheme' => 'file', 'host' => null],
            'file:\\\\' => ['scheme' => 'file', 'host' => null],
            'file:\\\\?fox' => ['scheme' => 'file', 'host' => null],
            'file:\\\\#guppy' => ['scheme' => 'file', 'host' => null],
            'file://spider///' => ['scheme' => 'file', 'host' => 'spider'],
            'file:\\localhost//' => ['scheme' => 'file', 'host' => null],
            'file:///localhost//cat' => ['scheme' => 'file', 'host' => ''],
            'file://\\/localhost//cat' => null,
            'file://localhost//a//../..//' => ['scheme' => 'file', 'host' => 'localhost'],
            '/////mouse' => ['scheme' => null, 'host' => ''],
            '\\//pig' => ['scheme' => null, 'host' => null],
            '\\/localhost//pig' => ['scheme' => null, 'host' => null],
            '//localhost//pig' => ['scheme' => null, 'host' => 'localhost'],
            '/..//localhost//pig' => ['scheme' => null, 'host' => null],
            'file://' => ['scheme' => 'file', 'host' => ''],
            '/rooibos' => ['scheme' => null, 'host' => null],
            '/?chai' => ['scheme' => null, 'host' => null],
            'C|' => ['scheme' => null, 'host' => null],
            'C|#' => ['scheme' => null, 'host' => null],
            'C|?' => ['scheme' => null, 'host' => null],
            'C|/' => ['scheme' => null, 'host' => null],
            "C|\n/" => null,
            'C|\\' => ['scheme' => null, 'host' => null],
            'C' => ['scheme' => null, 'host' => null],
            'C|a' => ['scheme' => null, 'host' => null],
            '/c:/foo/bar' => ['scheme' => null, 'host' => null],
            '/c|/foo/bar' => ['scheme' => null, 'host' => null],
            "file:\c:\foo\bar" => null,
            'file://example.net/C:/' => ['scheme' => 'file', 'host' => 'example.net'],
            'file://1.2.3.4/C:/' => ['scheme' => 'file', 'host' => '1.2.3.4'],
            'file://[1::8]/C:/' => ['scheme' => 'file', 'host' => '[1::8]'],
            'file:/C|/' => ['scheme' => 'file', 'host' => null],
            'file://C|/' => null,
            'file:?q=v' => ['scheme' => 'file', 'host' => null],
            'file:#frag' => ['scheme' => 'file', 'host' => null],
            'http://[1:0::]' => ['scheme' => 'http', 'host' => '[1:0::]'],
            'sc://ñ' => ['scheme' => 'sc', 'host' => 'ñ'],
            'sc://ñ?x' => ['scheme' => 'sc', 'host' => 'ñ'],
            'sc://ñ#x' => ['scheme' => 'sc', 'host' => 'ñ'],
            'sc://?' => ['scheme' => 'sc', 'host' => ''],
            'sc://#' => ['scheme' => 'sc', 'host' => ''],
            '////' => ['scheme' => null, 'host' => ''],
            '////x/' => ['scheme' => null, 'host' => ''],
            'tftp://foobar.com/someconfig;mode=netascii' => ['scheme' => 'tftp', 'host' => 'foobar.com'],
            'telnet://user:pass@foobar.com:23/' => ['scheme' => 'telnet', 'host' => 'foobar.com'],
            'ut2004://10.10.10.10:7777/Index.ut2' => ['scheme' => 'ut2004', 'host' => '10.10.10.10'],
            'redis://foo:bar@somehost:6379/0?baz=bam&qux=baz' => ['scheme' => 'redis', 'host' => 'somehost'],
            'rsync://foo@host:911/sup' => ['scheme' => 'rsync', 'host' => 'host'],
            'git://github.com/foo/bar.git' => ['scheme' => 'git', 'host' => 'github.com'],
            'irc://myserver.com:6999/channel?passwd' => ['scheme' => 'irc', 'host' => 'myserver.com'],
            'dns://fw.example.org:9999/foo.bar.org?type=TXT' => ['scheme' => 'dns', 'host' => 'fw.example.org'],
            'ldap://localhost:389/ou=People,o=JNDITutorial' => ['scheme' => 'ldap', 'host' => 'localhost'],
            'git+https://github.com/foo/bar' => ['scheme' => 'git+https', 'host' => 'github.com'],
            'urn:ietf:rfc:2648' => ['scheme' => 'urn', 'host' => null],
            'tag:joe@example.org,2001:foo/bar' => ['scheme' => 'tag', 'host' => null],
            'non-special://%E2%80%A0/' => ['scheme' => 'non-special', 'host' => '%E2%80%A0'],
            'non-special://H%4fSt/path' => ['scheme' => 'non-special', 'host' => 'H%4fSt'],
            'non-special://[1:2:0:0:5:0:0:0]/' => ['scheme' => 'non-special', 'host' => '[1:2:0:0:5:0:0:0]'],
            'non-special://[1:2:0:0:0:0:0:3]/' => ['scheme' => 'non-special', 'host' => '[1:2:0:0:0:0:0:3]'],
            'non-special://[1:2::3]:80/' => ['scheme' => 'non-special', 'host' => '[1:2::3]'],
            'blob:https://example.com:443/' => ['scheme' => 'blob', 'host' => null],
            'blob:d3958f5c-0777-0845-9dcf-2cb28783acaf' => ['scheme' => 'blob', 'host' => null],
            'http://0177.0.0.0189' => ['scheme' => 'http', 'host' => '0177.0.0.0189'],
            'http://0x7f.0.0.0x7g' => ['scheme' => 'http', 'host' => '0x7f.0.0.0x7g'],
            'http://0X7F.0.0.0X7G' => ['scheme' => 'http', 'host' => '0X7F.0.0.0X7G'],
            'http://[0:1:0:1:0:1:0:1]' => ['scheme' => 'http', 'host' => '[0:1:0:1:0:1:0:1]'],
            'http://[1:0:1:0:1:0:1:0]' => ['scheme' => 'http', 'host' => '[1:0:1:0:1:0:1:0]'],
            'http://example.org/test?"' => ['scheme' => 'http', 'host' => 'example.org'],
            'http://example.org/test?#' => ['scheme' => 'http', 'host' => 'example.org'],
            'http://example.org/test?<' => ['scheme' => 'http', 'host' => 'example.org'],
            'http://example.org/test?>' => ['scheme' => 'http', 'host' => 'example.org'],
            'http://example.org/test?⌣' => ['scheme' => 'http', 'host' => 'example.org'],
            'http://example.org/test?%23%23' => ['scheme' => 'http', 'host' => 'example.org'],
            'http://example.org/test?%GH' => ['scheme' => 'http', 'host' => 'example.org'],
            'http://example.org/test?a#%EF' => ['scheme' => 'http', 'host' => 'example.org'],
            'http://example.org/test?a#%GH' => ['scheme' => 'http', 'host' => 'example.org'],
            'test-a-colon-slash.html' => ['scheme' => null, 'host' => null],
            'test-a-colon-slash-slash.html' => ['scheme' => null, 'host' => null],
            'test-a-colon-slash-b.html' => ['scheme' => null, 'host' => null],
            'test-a-colon-slash-slash-b.html' => ['scheme' => null, 'host' => null],
            'http://example.org/test?a#bc' => ['scheme' => 'http', 'host' => 'example.org'],
            'http:\\/\\/f:b\\/c' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/f: \\/c' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/f:fifty-two\\/c' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/f:999999\\/c' => ['scheme' => 'http', 'host' => null],
            'non-special:\\/\\/f:999999\\/c' => ['scheme' => 'non-special', 'host' => null],
            'http:\\/\\/f: 21 \\/ b ? d # e ' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/[1::2]:3:4' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/2001::1' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/2001::1]' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/2001::1]:80' => ['scheme' => 'http', 'host' => null],
            'file:\\/\\/example:1\\/' => ['scheme' => 'file', 'host' => null],
            'file:\\/\\/example:test\\/' => ['scheme' => 'file', 'host' => null],
            'file:\\/\\/example%\\/' => ['scheme' => 'file', 'host' => null],
            'file:\\/\\/[example]\\/' => ['scheme' => 'file', 'host' => null],
            'http:\\/\\/user:pass@\\/' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/foo:-80\\/' => ['scheme' => 'http', 'host' => null],
            'http:\\/:@\\/www.example.com' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/user@\\/www.example.com' => ['scheme' => 'http', 'host' => null],
            'http:@\\/www.example.com' => ['scheme' => 'http', 'host' => null],
            'http:\\/@\\/www.example.com' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/@\\/www.example.com' => ['scheme' => 'http', 'host' => null],
            'https:@\\/www.example.com' => ['scheme' => 'https', 'host' => null],
            'http:a:b@\\/www.example.com' => ['scheme' => 'http', 'host' => null],
            'http:\\/a:b@\\/www.example.com' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/a:b@\\/www.example.com' => ['scheme' => 'http', 'host' => null],
            'http::@\\/www.example.com' => ['scheme' => 'http', 'host' => null],
            'http:@:www.example.com' => ['scheme' => 'http', 'host' => null],
            'http:\\/@:www.example.com' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/@:www.example.com' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/example example.com' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/Goo%20 goo%7C|.com' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/[]' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/[:]' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/GOO\\u00a0\\u3000goo.com' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/\\ufdd0zyx.com' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/%ef%b7%90zyx.com' => ['scheme' => 'http', 'host' => null],
            'https:\\/\\/\\ufffd' => ['scheme' => 'https', 'host' => null],
            'https:\\/\\/%EF%BF%BD' => ['scheme' => 'https', 'host' => null],
            'http:\\/\\/\\uff05\\uff14\\uff11.com' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/%ef%bc%85%ef%bc%94%ef%bc%91.com' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/\\uff05\\uff10\\uff10.com' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/%ef%bc%85%ef%bc%90%ef%bc%90.com' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/%zz%66%a.com' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/%25' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/hello%00' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/192.168.0.257' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/%3g%78%63%30%2e%30%32%35%30%2E.01' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/192.168.0.1 hello' => ['scheme' => 'http', 'host' => null],
            'https:\\/\\/x x:12' => ['scheme' => 'https', 'host' => null],
            'http:\\/\\/[www.google.com]\\/' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/[google.com]' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/[::1.2.3.4x]' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/[::1.2.3.]' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/[::1.2.]' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/[::1.]' => ['scheme' => 'http', 'host' => null],
            '..\\/i' => ['scheme' => null, 'host' => null],
            '\\/i' => ['scheme' => null, 'host' => null],
            'sc:\\/\\/\\u0000\\/' => ['scheme' => 'sc', 'host' => null],
            'sc:\\/\\/ \\/' => ['scheme' => 'sc', 'host' => null],
            'sc:\\/\\/@\\/' => ['scheme' => 'sc', 'host' => null],
            'sc:\\/\\/te@s:t@\\/' => ['scheme' => 'sc', 'host' => null],
            'sc:\\/\\/:\\/' => ['scheme' => 'sc', 'host' => null],
            'sc:\\/\\/:12\\/' => ['scheme' => 'sc', 'host' => null],
            'sc:\\/\\/[\\/' => ['scheme' => 'sc', 'host' => null],
            'sc:\\/\\/\\\\/' => ['scheme' => 'sc', 'host' => null],
            'sc:\\/\\/]\\/' => ['scheme' => 'sc', 'host' => null],
            'ftp:\\/\\/example.com%80\\/' => ['scheme' => 'ftp', 'host' => null],
            'ftp:\\/\\/example.com%A0\\/' => ['scheme' => 'ftp', 'host' => null],
            'https:\\/\\/example.com%80\\/' => ['scheme' => 'https', 'host' => null],
            'https:\\/\\/example.com%A0\\/' => ['scheme' => 'https', 'host' => null],
            'http:\\/\\/10000000000' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/4294967296' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/0xffffffff1' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/256.256.256.256' => ['scheme' => 'http', 'host' => null],
            'https:\\/\\/0x100000000\\/test' => ['scheme' => 'https', 'host' => null],
            'https:\\/\\/256.0.0.1\\/test' => ['scheme' => 'https', 'host' => null],
            'http:\\/\\/[0:1:2:3:4:5:6:7:8]' => ['scheme' => 'http', 'host' => null],
            'https:\\/\\/[0::0::0]' => ['scheme' => 'https', 'host' => null],
            'https:\\/\\/[0:.0]' => ['scheme' => 'https', 'host' => null],
            'https:\\/\\/[0:0:]' => ['scheme' => 'https', 'host' => null],
            'https:\\/\\/[0:1:2:3:4:5:6:7.0.0.0.1]' => ['scheme' => 'https', 'host' => null],
            'https:\\/\\/[0:1.00.0.0.0]' => ['scheme' => 'https', 'host' => null],
            'https:\\/\\/[0:1.290.0.0.0]' => ['scheme' => 'https', 'host' => null],
            'https:\\/\\/[0:1.23.23]' => ['scheme' => 'https', 'host' => null],
            'http:\\/\\/?' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/#' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/f:4294967377\\/c' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/f:18446744073709551697\\/c' => ['scheme' => 'http', 'host' => null],
            'http:\\/\\/f:340282366920938463463374607431768211537\\/c' => ['scheme' => 'http', 'host' => null],
            'non-special:\\/\\/[:80\\/' => ['scheme' => 'non-special', 'host' => null],
            'http:\\/\\/[::127.0.0.0.1]' => ['scheme' => 'http', 'host' => null],
            'a' => ['scheme' => null, 'host' => null],
            'a\\/' => ['scheme' => null, 'host' => null],
            'a\\/\\/' => ['scheme' => null, 'host' => null],
            'test-a-colon.html' => ['scheme' => null, 'host' => null],
            'test-a-colon-b.html' => ['scheme' => null, 'host' => null],
        ];

        foreach ($urls as $url => $expected) {
            yield $url => [$url, $expected];
        }
    }
}
