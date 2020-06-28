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

use PHPUnit\Framework\MockObject\MockObject;
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
     *
     * @param string|string[] $baseUrls
     * @param string          $format
     * @param string          $path
     * @param string          $expected
     */
    public function testGetUrl($baseUrls, $format, $path, $expected)
    {
        $package = new UrlPackage($baseUrls, new StaticVersionStrategy('v1', $format));
        $this->assertSame($expected, $package->getUrl($path));
    }

    /**
     * @return array[]
     */
    public function getConfigs()
    {
        return [
            ['http://example.net', '', 'http://example.com/foo', 'http://example.com/foo'],
            ['http://example.net', '', 'https://example.com/foo', 'https://example.com/foo'],
            ['http://example.net', '', '//example.com/foo', '//example.com/foo'],

            ['http://example.com', '', '/foo', 'http://example.com/foo?v1'],
            ['http://example.com', '', 'foo', 'http://example.com/foo?v1'],
            ['http://example.com/', '', 'foo', 'http://example.com/foo?v1'],
            ['http://example.com/foo', '', 'foo', 'http://example.com/foo/foo?v1'],
            ['http://example.com/foo/', '', 'foo', 'http://example.com/foo/foo?v1'],

            [['http://example.com'], '', '/foo', 'http://example.com/foo?v1'],
            [['http://example.com', 'http://example.net'], '', '/foo', 'http://example.com/foo?v1'],
            [['http://example.com', 'http://example.net'], '', '/fooa', 'http://example.net/fooa?v1'],

            ['http://example.com', 'version-%2$s/%1$s', '/foo', 'http://example.com/version-v1/foo'],
            ['http://example.com', 'version-%2$s/%1$s', 'foo', 'http://example.com/version-v1/foo'],
            ['http://example.com', 'version-%2$s/%1$s', 'foo/', 'http://example.com/version-v1/foo/'],
            ['http://example.com', 'version-%2$s/%1$s', '/foo/', 'http://example.com/version-v1/foo/'],
        ];
    }

    /**
     * @dataProvider getContextConfigs
     *
     * @param bool            $secure
     * @param string|string[] $baseUrls
     * @param string          $format
     * @param string          $path
     * @param string          $expected
     */
    public function testGetUrlWithContext($secure, $baseUrls, $format, $path, $expected)
    {
        $package = new UrlPackage($baseUrls, new StaticVersionStrategy('v1', $format), $this->getContext($secure));

        $this->assertSame($expected, $package->getUrl($path));
    }

    /**
     * @return array[]
     */
    public function getContextConfigs()
    {
        return [
            [false, 'http://example.com', '', 'foo', 'http://example.com/foo?v1'],
            [false, ['http://example.com'], '', 'foo', 'http://example.com/foo?v1'],
            [false, ['http://example.com', 'https://example.com'], '', 'foo', 'http://example.com/foo?v1'],
            [false, ['http://example.com', 'https://example.com'], '', 'fooa', 'https://example.com/fooa?v1'],
            [false, ['http://example.com/bar'], '', 'foo', 'http://example.com/bar/foo?v1'],
            [false, ['http://example.com/bar/'], '', 'foo', 'http://example.com/bar/foo?v1'],
            [false, ['//example.com/bar/'], '', 'foo', '//example.com/bar/foo?v1'],

            [true, ['http://example.com'], '', 'foo', 'http://example.com/foo?v1'],
            [true, ['http://example.com', 'https://example.com'], '', 'foo', 'https://example.com/foo?v1'],
        ];
    }

    public function testVersionStrategyGivesAbsoluteURL()
    {
        $versionStrategy = $this->getMockBuilder(VersionStrategyInterface::class)->getMock();
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

    public function testWrongBaseUrl()
    {
        $this->expectException(InvalidArgumentException::class);
        new UrlPackage(['not-a-url'], new EmptyVersionStrategy());
    }

    /**
     * @param bool $secure
     *
     * @return MockObject&ContextInterface
     */
    private function getContext($secure)
    {
        $context = $this->getMockBuilder(ContextInterface::class)->getMock();
        $context->expects($this->any())->method('isSecure')->willReturn($secure);

        return $context;
    }
}
