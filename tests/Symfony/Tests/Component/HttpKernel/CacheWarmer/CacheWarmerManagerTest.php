<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpKernel\CacheWarmer;

use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerManager;

class CacheWarmerManagerTest extends \PHPUnit_Extensions_OutputTestCase  //\PHPUnit_Framework_TestCase
{
    protected static $cacheDir;

    public static function setUpBeforeClass()
    {
        self::$cacheDir = tempnam(sys_get_temp_dir(), 'sf2_cache_warmer_dir');
    }

    public function testInjectWarmersUsingConstructor()
    {
        $warmer = $this->getCacheWarmerMock();
        $warmer
            ->expects($this->once())
            ->method('warmUp');
        $manager = new CacheWarmerManager(array($warmer));
        $manager->warmUp(self::$cacheDir);
    }

    public function testInjectWarmersUsingAdd()
    {
        $warmer = $this->getCacheWarmerMock();
        $warmer
            ->expects($this->once())
            ->method('warmUp');
        $manager = new CacheWarmerManager();
        $manager->add($warmer);
        $manager->warmUp(self::$cacheDir);
    }

    public function testInjectWarmersUsingSetWarmers()
    {
        $warmer = $this->getCacheWarmerMock();
        $warmer
            ->expects($this->once())
            ->method('warmUp');
        $manager = new CacheWarmerManager();
        $manager->setWarmers(array($warmer));
        $manager->warmUp(self::$cacheDir);
    }

    public function testWarmupDoesCallWarmupOnOptionalWarmersWhenEnableOptionalWarmersIsEnabled()
    {
        $warmer = $this->getCacheWarmerMock();
        $warmer
            ->expects($this->never())
            ->method('isOptional');
        $warmer
            ->expects($this->once())
            ->method('warmUp');

        $manager = new CacheWarmerManager(array($warmer));
        $manager->enableOptionalWarmers();
        $manager->warmUp(self::$cacheDir);
    }

    public function testWarmupDoesNotCallWarmupOnOptionalWarmersWhenEnableOptionalWarmersIsNotEnabled()
    {
        $warmer = $this->getCacheWarmerMock();
        $warmer
            ->expects($this->once())
            ->method('isOptional')
            ->will($this->returnValue(true));
        $warmer
            ->expects($this->never())
            ->method('warmUp');

        $manager = new CacheWarmerManager(array($warmer));
        $manager->warmUp(self::$cacheDir);
    }
    
    /**
     * @expectedException \RuntimeException
     */
    public function testThrowsAnExceptionOnDuplicateWarmerName()
    {
        new CacheWarmerManager(array($this->getCacheWarmerMock(), $this->getCacheWarmerMock()));        
    }

    /**
     * @expectedException \RuntimeException
     */    
    public function testThrowsAnExceptionOnCiruclarReferenceUsingPreWarmers()
    {
        $manager = new CacheWarmerManager(array(
            $this->getCacheWarmerMock('foo', array('bar')),
            $this->getCacheWarmerMock('bar', array('foo')),
        ));
        
        $manager->warmUp(self::$cacheDir);               
    }

    /**
     * @expectedException \RuntimeException
     */    
    public function testThrowsAnExceptionOnCiruclarReferenceUsingPostWarmers()
    {
        $manager = new CacheWarmerManager(array(
            $this->getCacheWarmerMock('foo', array(), array('bar')),
            $this->getCacheWarmerMock('bar', array(), array('foo')),
        ));
        
        $manager->warmUp(self::$cacheDir);               
    }
    
    public function testWarmUpWarmersWithoutDependency()
    {
        $this->expectOutputString('foobarbaz');
                
        $manager = new CacheWarmerManager(array(
            $this->getCacheWarmerMock('foo', array(), array(), true),
            $this->getCacheWarmerMock('bar', array(), array(), true),
            $this->getCacheWarmerMock('baz', array(), array(), true),
        ));

        $manager->warmUp(self::$cacheDir);        
    }

    public function testWarmUpWarmersWithTheSameDependency()
    {
        $this->expectOutputString('bazfoobar');
                
        $manager = new CacheWarmerManager(array(
            $this->getCacheWarmerMock('foo', array('baz'), array(), true),
            $this->getCacheWarmerMock('bar', array('baz'), array(), true),
            $this->getCacheWarmerMock('baz', array(), array(), true),
        ));

        $manager->warmUp(self::$cacheDir);        
    }

    public function testWarmUpWarmersWithCascadingDependencies()
    {
        $this->expectOutputString('bazbarfoo');
                
        $manager = new CacheWarmerManager(array(
            $this->getCacheWarmerMock('foo', array('bar'), array(), true),
            $this->getCacheWarmerMock('bar', array('baz'), array(), true),
            $this->getCacheWarmerMock('baz', array(), array(), true),
        ));

        $manager->warmUp(self::$cacheDir);        
    }

    public function testWarmUpAWarmerWithAPreWarmerAndAPostWarmer()
    {
        $this->expectOutputString('bazbarfoo');
                
        $manager = new CacheWarmerManager(array(
            $this->getCacheWarmerMock('foo', array(), array(), true),
            $this->getCacheWarmerMock('bar', array('baz'), array('foo'), true),
            $this->getCacheWarmerMock('baz', array(), array(), true),
        ));

        $manager->warmUp(self::$cacheDir);        
    }
    
    public function testIgnoreUnregisteredWarmers()
    {
        $this->expectOutputString('foobarbaz');
                
        $manager = new CacheWarmerManager(array(
            $this->getCacheWarmerMock('foo', array(), array(), true),
            $this->getCacheWarmerMock('bar', array('foobar'), array(), true),
            $this->getCacheWarmerMock('baz', array(), array('barfoo'), true),
        ));

        $manager->warmUp(self::$cacheDir);                
    }
    
    protected function getCacheWarmerMock($name = 'test.warmer', $preWarmers = array(), $postWarmers = array(), $output = false)
    {
        $warmer = $this
            ->getMockBuilder('Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface')
            ->disableOriginalConstructor()
            ->getMock()
        ;
                
        $warmer
            ->expects($this->any())
            ->method('getName')
            ->will($this->returnValue($name))
        ;

        $warmer
            ->expects($this->any())
            ->method('getPreWarmers')
            ->will($this->returnValue($preWarmers))
        ;

        $warmer
            ->expects($this->any())
            ->method('getPostWarmers')
            ->will($this->returnValue($postWarmers))
        ;
        
        if ($output) {
            $warmer
                ->expects($this->any())
                ->method('warmUp')
                ->will($this->returnCallback(function() use ($name) { echo $name; }))
            ;            
        }
        
        return $warmer;
    }
}
