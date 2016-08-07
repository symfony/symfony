<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests;

use Symfony\Component\Config\Tests\Resource\ResourceStub;
use Symfony\Component\Config\ResourceCheckerConfigCache;

class ResourceCheckerConfigCacheTest extends \PHPUnit_Framework_TestCase
{
    private $cacheFile = null;

    protected function setUp()
    {
        $this->cacheFile = tempnam(sys_get_temp_dir(), 'config_');
    }

    protected function tearDown()
    {
        $files = array($this->cacheFile, "{$this->cacheFile}.meta");

        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    public function testGetPath()
    {
        $cache = new ResourceCheckerConfigCache($this->cacheFile);

        $this->assertSame($this->cacheFile, $cache->getPath());
    }

    public function testCacheIsNotFreshIfEmpty()
    {
        $checker = $this->getMock('\Symfony\Component\Config\ResourceCheckerInterface')
            ->expects($this->never())->method('supports');

        /* If there is nothing in the cache, it needs to be filled (and thus it's not fresh).
            It does not matter if you provide checkers or not. */

        unlink($this->cacheFile); // remove tempnam() side effect
        $cache = new ResourceCheckerConfigCache($this->cacheFile, array($checker));

        $this->assertFalse($cache->isFresh());
    }

    public function testCacheIsFreshIfNocheckerProvided()
    {
        /* For example in prod mode, you may choose not to run any checkers
           at all. In that case, the cache should always be considered fresh. */
        $cache = new ResourceCheckerConfigCache($this->cacheFile);
        $this->assertTrue($cache->isFresh());
    }

    public function testResourcesWithoutcheckersAreIgnoredAndConsideredFresh()
    {
        /* As in the previous test, but this time we have a resource. */
        $cache = new ResourceCheckerConfigCache($this->cacheFile);
        $cache->write('', array(new ResourceStub()));

        $this->assertTrue($cache->isFresh()); // no (matching) ResourceChecker passed
    }

    public function testIsFreshWithchecker()
    {
        $checker = $this->getMock('\Symfony\Component\Config\ResourceCheckerInterface');

        $checker->expects($this->once())
                  ->method('supports')
                  ->willReturn(true);

        $checker->expects($this->once())
                  ->method('isFresh')
                  ->willReturn(true);

        $cache = new ResourceCheckerConfigCache($this->cacheFile, array($checker));
        $cache->write('', array(new ResourceStub()));

        $this->assertTrue($cache->isFresh());
    }

    public function testIsNotFreshWithchecker()
    {
        $checker = $this->getMock('\Symfony\Component\Config\ResourceCheckerInterface');

        $checker->expects($this->once())
                  ->method('supports')
                  ->willReturn(true);

        $checker->expects($this->once())
                  ->method('isFresh')
                  ->willReturn(false);

        $cache = new ResourceCheckerConfigCache($this->cacheFile, array($checker));
        $cache->write('', array(new ResourceStub()));

        $this->assertFalse($cache->isFresh());
    }

    public function testCacheKeepsContent()
    {
        $cache = new ResourceCheckerConfigCache($this->cacheFile);
        $cache->write('FOOBAR');

        $this->assertSame('FOOBAR', file_get_contents($cache->getPath()));
    }
}
