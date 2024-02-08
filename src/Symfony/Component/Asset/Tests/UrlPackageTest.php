<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Context\ContextInterface;
use Symfony\Component\Asset\Exception\InvalidArgumentException;
use Symfony\Component\Asset\Exception\LogicException;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

class UrlPackageTest extends TestCase
{
    /**
     * @dataProvider getConfigs
     */
    public function testGetUrl($baseUrls, string $format, string $path, string $expected)
    {
        $package = new UrlPackage($baseUrls, new StaticVersionStrategy('v1', $format));
        $this->assertSame($expected, $package->getUrl($path));
    }

    public static function getConfigs(): array
    {
        return [
            ['http://example.net', '', 'http://example.com/foo', 'http://example.com/foo'],
            ['http://example.net', '', 'https://example.com/foo', 'https://example.com/foo'],
            ['http://example.net', '', '//example.com/foo', '//example.com/foo'],
            ['file:///example/net', '', 'file:///example/com/foo', 'file:///example/com/foo'],
            ['ftp://example.net', '', 'ftp://example.com', 'ftp://example.com'],

            ['http://example.com', '', '/foo', 'http://example.com/foo?v1'],
            ['http://example.com', '', 'foo', 'http://example.com/foo?v1'],
            ['http://example.com/', '', 'foo', 'http://example.com/foo?v1'],
            ['http://example.com/foo', '', 'foo', 'http://example.com/foo/foo?v1'],
            ['http://example.com/foo/', '', 'foo', 'http://example.com/foo/foo?v1'],
            ['file:///example/com/foo/', '', 'foo', 'file:///example/com/foo/foo?v1'],

            [['http://example.com'], '', '/foo', 'http://example.com/foo?v1'],
            [['http://example.com', 'http://example.net'], '', '/foo', 'http://example.net/foo?v1'],
            [['http://example.com', 'http://example.net'], '', '/fooa', 'http://example.com/fooa?v1'],
            [['file:///example/com', 'file:///example/net'], '', '/foo', 'file:///example/net/foo?v1'],
            [['ftp://example.com', 'ftp://example.net'], '', '/fooa', 'ftp://example.com/fooa?v1'],

            ['http://example.com', 'version-%2$s/%1$s', '/foo', 'http://example.com/version-v1/foo'],
            ['http://example.com', 'version-%2$s/%1$s', 'foo', 'http://example.com/version-v1/foo'],
            ['http://example.com', 'version-%2$s/%1$s', 'foo/', 'http://example.com/version-v1/foo/'],
            ['http://example.com', 'version-%2$s/%1$s', '/foo/', 'http://example.com/version-v1/foo/'],
            ['file:///example/com', 'version-%2$s/%1$s', '/foo/', 'file:///example/com/version-v1/foo/'],
            ['ftp://example.com', 'version-%2$s/%1$s', '/foo/', 'ftp://example.com/version-v1/foo/'],
        ];
    }

    /**
     * @dataProvider getContextConfigs
     */
    public function testGetUrlWithContext(bool $secure, $baseUrls, string $format, string $path, string $expected)
    {
        $package = new UrlPackage($baseUrls, new StaticVersionStrategy('v1', $format), $this->getContext($secure));

        $this->assertSame($expected, $package->getUrl($path));
    }

    public static function getContextConfigs(): array
    {
        return [
            [false, 'http://example.com', '', 'foo', 'http://example.com/foo?v1'],
            [false, ['http://example.com'], '', 'foo', 'http://example.com/foo?v1'],
            [false, ['http://example.com', 'https://example.com'], '', 'foo', 'https://example.com/foo?v1'],
            [false, ['http://example.com', 'https://example.com'], '', 'fooa', 'http://example.com/fooa?v1'],
            [false, ['http://example.com/bar'], '', 'foo', 'http://example.com/bar/foo?v1'],
            [false, ['http://example.com/bar/'], '', 'foo', 'http://example.com/bar/foo?v1'],
            [false, ['//example.com/bar/'], '', 'foo', '//example.com/bar/foo?v1'],

            [true, ['http://example.com'], '', 'foo', 'http://example.com/foo?v1'],
            [true, ['http://example.com', 'https://example.com'], '', 'foo', 'https://example.com/foo?v1'],
            [true, ['', 'https://example.com'], '', 'foo', 'https://example.com/foo?v1'],
            [true, ['', 'https://example.com'], '', 'bar', '/bar?v1'],
        ];
    }

    public function testVersionStrategyGivesAbsoluteURL()
    {
        $versionStrategy = $this->createMock(VersionStrategyInterface::class);
        $versionStrategy->expects($this->any())
            ->method('applyVersion')
            ->willReturn('https://cdn.com/bar/main.css');
        $package = new UrlPackage('https://example.com', $versionStrategy);

        $this->assertSame('https://cdn.com/bar/main.css', $package->getUrl('main.css'));
    }

    public function testNoBaseUrls()
    {
        $this->expectException(LogicException::class);
        new UrlPackage([], new EmptyVersionStrategy());
    }

    /**
     * @dataProvider getWrongBaseUrlConfig
     */
    public function testWrongBaseUrl(string $baseUrls)
    {
        $this->expectException(InvalidArgumentException::class);
        new UrlPackage($baseUrls, new EmptyVersionStrategy());
    }

    public static function getWrongBaseUrlConfig(): array
    {
        return [
            ['not-a-url'],
            ['not-a-url-with-query?query=://'],
        ];
    }

    private function getContext($secure): ContextInterface
    {
        $context = $this->createMock(ContextInterface::class);
        $context->expects($this->any())->method('isSecure')->willReturn($secure);

        return $context;
    }
}
