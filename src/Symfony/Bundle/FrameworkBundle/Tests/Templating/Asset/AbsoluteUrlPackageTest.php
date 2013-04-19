<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Templating\Asset;

use Symfony\Component\Routing\RequestContext;
use Symfony\Bundle\FrameworkBundle\Templating\Asset\AbsoluteUrlPackage;

/**
 * This package attempts to return absolute URL for assets
 *
 * @author Roman Marintšenko <roman.marintsenko@knplabs.com>
 */
class AbsoluteUrlPackageTest extends \PHPUnit_Framework_TestCase
{
    public function testGetUrl()
    {
        $package = new AbsoluteUrlPackage(new RequestContext());
        $this->assertEquals('http://example.com/foo.js', $package->getUrl('http://example.com/foo.js'), '->getUrl() does nothing if an absolute URL is already given');
        
        $package = new AbsoluteUrlPackage(new RequestContext('', 'GET', 'symfony.com'));
        $this->assertEquals('http://symfony.com/foo.js', $package->getUrl('foo.js'), '->getUrl() prepends host and scheme to a given path');
        $this->assertEquals('http://symfony.com/foo.js', $package->getUrl('/foo.js'), '->getUrl() prepends host and scheme to a given absolute path');

        $package = new AbsoluteUrlPackage(new RequestContext('foo', 'GET', 'symfony.com'));
        $this->assertEquals('http://symfony.com/foo/foo.js', $package->getUrl('foo.js'), '->getUrl() prepends base url to relative path');
        $this->assertEquals('http://symfony.com/foo/foo.js', $package->getUrl('/foo.js'), '->getUrl() prepends base url to absolute path');
        
        $package = new AbsoluteUrlPackage(new RequestContext('/foo', 'GET', 'symfony.com'));
        $this->assertEquals('http://symfony.com/foo/foo.js', $package->getUrl('foo.js'), '->getUrl() prepends base url with backslash at beginning to relative path');
        
        $package = new AbsoluteUrlPackage(new RequestContext('foo/', 'GET', 'symfony.com'));
        $this->assertEquals('http://symfony.com/foo/foo.js', $package->getUrl('foo.js'), '->getUrl() prepends base url with backslash at end to relative path');

        $package = new AbsoluteUrlPackage(new RequestContext('', 'GET', 'symfony.com', 'http', 8080));
        $this->assertEquals('http://symfony.com:8080/foo.js', $package->getUrl('foo.js'), '->getUrl() prepends port if it is different than 80');
        
        $package = new AbsoluteUrlPackage(new RequestContext('', 'GET', 'symfony.com', 'https', 80, 443));
        $this->assertEquals('https://symfony.com/foo.js', $package->getUrl('foo.js'), '->getUrl() does not prepend 443 port if scheme is https');
        
        $package = new AbsoluteUrlPackage(new RequestContext('', 'GET', 'symfony.com', 'https', 80, 444));
        $this->assertEquals('https://symfony.com:444/foo.js', $package->getUrl('foo.js'), '->getUrl() prepends port if scheme is https and it is different than 443');

        $package = new AbsoluteUrlPackage(new RequestContext('', 'GET', 'symfony.com'), 'abcd');
        $this->assertEquals('http://symfony.com/foo.js?abcd', $package->getUrl('foo.js'), '->getUrl() appends the version if defined');
    }
}