<?php

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Templating\Helper;

require_once __DIR__.'/../../../bootstrap.php';

use Symfony\Components\Templating\Helper\AssetsHelper;

class AssetsHelperTest extends \PHPUnit_Framework_TestCase
{
  public function testConstructor()
  {
    $helper = new AssetsHelper('foo', 'http://www.example.com', 'abcd');
    $this->assertEquals($helper->getBasePath(), '/foo/', '__construct() takes a base path as its first argument');
    $this->assertEquals($helper->getBaseURLs(), array('http://www.example.com'), '__construct() takes a base URL as its second argument');
    $this->assertEquals($helper->getVersion(), 'abcd', '__construct() takes a version as its thrid argument');
  }

  public function testGetSetBasePath()
  {
    $helper = new AssetsHelper();
    $helper->setBasePath('foo/');
    $this->assertEquals($helper->getBasePath(), '/foo/', '->setBasePath() prepends a / if needed');
    $helper->setBasePath('/foo');
    $this->assertEquals($helper->getBasePath(), '/foo/', '->setBasePath() appends a / is needed');
    $helper->setBasePath('');
    $this->assertEquals($helper->getBasePath(), '/', '->setBasePath() returns / if no base path is defined');
    $helper->setBasePath('0');
    $this->assertEquals($helper->getBasePath(), '/0/', '->setBasePath() returns /0/ if 0 is given');
  }

  public function testGetSetVersion()
  {
    $helper = new AssetsHelper();
    $helper->setVersion('foo');
    $this->assertEquals($helper->getVersion(), 'foo', '->setVersion() sets the version');
  }

  public function testSetGetBaseURLs()
  {
    $helper = new AssetsHelper();
    $helper->setBaseURLs('http://www.example.com/');
    $this->assertEquals($helper->getBaseURLs(), array('http://www.example.com'), '->setBaseURLs() removes the / at the of an absolute base path');
    $helper->setBaseURLs(array('http://www1.example.com/', 'http://www2.example.com/'));
    $URLs = array();
    for ($i = 0; $i < 20; $i++)
    {
      $URLs[] = $helper->getBaseURL($i);
    }
    $URLs = array_values(array_unique($URLs));
    sort($URLs);
    $this->assertEquals($URLs, array('http://www1.example.com', 'http://www2.example.com'), '->getBaseURL() returns a random base URL if several are given');
    $helper->setBaseURLs('');
    $this->assertEquals($helper->getBaseURL(1), '', '->getBaseURL() returns an empty string if no base URL exist');
  }

  public function testGetUrl()
  {
    $helper = new AssetsHelper();
    $this->assertEquals($helper->getUrl('http://example.com/foo.js'), 'http://example.com/foo.js', '->getUrl() does nothing if an absolute URL is given');

    $helper = new AssetsHelper();
    $this->assertEquals($helper->getUrl('foo.js'), '/foo.js', '->getUrl() appends a / on relative paths');
    $this->assertEquals($helper->getUrl('/foo.js'), '/foo.js', '->getUrl() does nothing on absolute paths');

    $helper = new AssetsHelper('/foo');
    $this->assertEquals($helper->getUrl('foo.js'), '/foo/foo.js', '->getUrl() appends the basePath on relative paths');
    $this->assertEquals($helper->getUrl('/foo.js'), '/foo.js', '->getUrl() does not append the basePath on absolute paths');

    $helper = new AssetsHelper(null, 'http://assets.example.com/');
    $this->assertEquals($helper->getUrl('foo.js'), 'http://assets.example.com/foo.js', '->getUrl() prepends the base URL');
    $this->assertEquals($helper->getUrl('/foo.js'), 'http://assets.example.com/foo.js', '->getUrl() prepends the base URL');

    $helper = new AssetsHelper(null, 'http://www.example.com/foo');
    $this->assertEquals($helper->getUrl('foo.js'), 'http://www.example.com/foo/foo.js', '->getUrl() prepends the base URL with a path');
    $this->assertEquals($helper->getUrl('/foo.js'), 'http://www.example.com/foo/foo.js', '->getUrl() prepends the base URL with a path');

    $helper = new AssetsHelper('/foo', 'http://www.example.com/');
    $this->assertEquals($helper->getUrl('foo.js'), 'http://www.example.com/foo/foo.js', '->getUrl() prepends the base URL and the base path if defined');
    $this->assertEquals($helper->getUrl('/foo.js'), 'http://www.example.com/foo.js', '->getUrl() prepends the base URL but not the base path on absolute paths');

    $helper = new AssetsHelper('/bar', 'http://www.example.com/foo');
    $this->assertEquals($helper->getUrl('foo.js'), 'http://www.example.com/foo/bar/foo.js', '->getUrl() prepends the base URL and the base path if defined');
    $this->assertEquals($helper->getUrl('/foo.js'), 'http://www.example.com/foo/foo.js', '->getUrl() prepends the base URL but not the base path on absolute paths');

    $helper = new AssetsHelper('/bar', 'http://www.example.com/foo', 'abcd');
    $this->assertEquals($helper->getUrl('foo.js'), 'http://www.example.com/foo/bar/foo.js?abcd', '->getUrl() appends the version if defined');
  }
}
