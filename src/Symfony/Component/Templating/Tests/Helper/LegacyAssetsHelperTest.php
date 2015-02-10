<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Tests\Helper;

use Symfony\Component\Templating\Helper\AssetsHelper;

class LegacyAssetsHelperTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);
    }

    public function testGetVersion()
    {
        $helper = new AssetsHelper(null, array(), 'foo');
        $this->assertEquals('foo', $helper->getVersion(), '->getVersion() returns the version');
    }

    public function testGetUrl()
    {
        $helper = new AssetsHelper();
        $this->assertEquals('http://example.com/foo.js', $helper->getUrl('http://example.com/foo.js'), '->getUrl() does nothing if an absolute URL is given');

        $helper = new AssetsHelper();
        $this->assertEquals('/foo.js', $helper->getUrl('foo.js'), '->getUrl() appends a / on relative paths');
        $this->assertEquals('/foo.js', $helper->getUrl('/foo.js'), '->getUrl() does nothing on absolute paths');

        $helper = new AssetsHelper('/foo');
        $this->assertEquals('/foo/foo.js', $helper->getUrl('foo.js'), '->getUrl() appends the basePath on relative paths');
        $this->assertEquals('/foo.js', $helper->getUrl('/foo.js'), '->getUrl() does not append the basePath on absolute paths');

        $helper = new AssetsHelper(null, 'http://assets.example.com/');
        $this->assertEquals('http://assets.example.com/foo.js', $helper->getUrl('foo.js'), '->getUrl() prepends the base URL');
        $this->assertEquals('http://assets.example.com/foo.js', $helper->getUrl('/foo.js'), '->getUrl() prepends the base URL');

        $helper = new AssetsHelper(null, 'http://www.example.com/foo');
        $this->assertEquals('http://www.example.com/foo/foo.js', $helper->getUrl('foo.js'), '->getUrl() prepends the base URL with a path');
        $this->assertEquals('http://www.example.com/foo/foo.js', $helper->getUrl('/foo.js'), '->getUrl() prepends the base URL with a path');

        $helper = new AssetsHelper('/foo', 'http://www.example.com/');
        $this->assertEquals('http://www.example.com/foo.js', $helper->getUrl('foo.js'), '->getUrl() prepends the base URL and the base path if defined');
        $this->assertEquals('http://www.example.com/foo.js', $helper->getUrl('/foo.js'), '->getUrl() prepends the base URL but not the base path on absolute paths');

        $helper = new AssetsHelper('/bar', 'http://www.example.com/foo');
        $this->assertEquals('http://www.example.com/foo/foo.js', $helper->getUrl('foo.js'), '->getUrl() prepends the base URL and the base path if defined');
        $this->assertEquals('http://www.example.com/foo/foo.js', $helper->getUrl('/foo.js'), '->getUrl() prepends the base URL but not the base path on absolute paths');

        $helper = new AssetsHelper('/bar', 'http://www.example.com/foo', 'abcd');
        $this->assertEquals('http://www.example.com/foo/foo.js?abcd', $helper->getUrl('foo.js'), '->getUrl() appends the version if defined');

        $helper = new AssetsHelper();
        $this->assertEquals('/', $helper->getUrl(''), '->getUrl() with empty arg returns the prefix alone');
    }

    public function testGetUrlWithVersion()
    {
        $helper = new AssetsHelper(null, array(), '12');
        $this->assertEquals('/foo.js?12', $helper->getUrl('foo.js'));
        $this->assertEquals('/foo.js?bar', $helper->getUrl('foo.js', null, 'bar'));
        $this->assertEquals('/foo.js', $helper->getUrl('foo.js', null, false));
    }

    public function testGetUrlLeavesProtocolRelativePathsUntouched()
    {
        $helper = new AssetsHelper(null, 'http://foo.com');
        $this->assertEquals('//bar.com/asset', $helper->getUrl('//bar.com/asset'));
    }
}
