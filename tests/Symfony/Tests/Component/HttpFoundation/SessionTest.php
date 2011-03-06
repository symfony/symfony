<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\Session;
use Symfony\Component\HttpFoundation\SessionStorage\SessionStorageInterface;

/**
 * SessionTest
 *
 * @author Robert Schönthal <seroscho@googlemail.com>
 */
class SessionTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider provider
     */
    public function testFlash($s)
    {
        $this->assertFalse($s->hasFlash('foo'));
        
        $s->setFlash('foo', 'bar');
        
        $this->assertTrue($s->hasFlash('foo'));
        $this->assertSame('bar',$s->getFlash('foo'));
        
        $s->removeFlash('foo');
        
        $this->assertFalse($s->hasFlash('foo'));
        
        $flashes = array('foo'=>'bar','bar'=>'foo');
        
        $s->restart();
        $s->setFlashes($flashes);
        
        $this->assertSame($flashes,$s->getFlashes());
        
        $s->clearFlashes();
        
        $this->assertSame(array(),$s->getFlashes());
    }
    
    /**
     * @dataProvider provider
     */
    public function testAttribute($s)
    {        
        $this->assertFalse($s->has('foo'));
        $this->assertNull($s->get('foo'));
        
        $s->set('foo', 'bar');
        
        $this->assertTrue($s->has('foo'));
        $this->assertSame('bar',$s->get('foo'));
        
        $s->restart();
        
        $s->remove('foo');
        $s->set('foo', 'bar');
        
        $s->remove('foo');
        
        $this->assertFalse($s->has('foo'));
        
        $attrs = array('foo'=>'bar','bar'=>'foo');
        
        $s->restart();
        
        $s->setAttributes($attrs);
        
        $this->assertSame($attrs,$s->getAttributes());
        
        $s->clear();
        
        $this->assertSame(array(),$s->getAttributes());
    }
    
    /**
     * @dataProvider provider
     */
    public function testMigrateAndInvalidate($s)
    {
        $s->set('foo', 'bar');
        $s->setFlash('foo', 'bar');

        $this->assertSame('bar',$s->get('foo'));
        $this->assertSame('bar',$s->getFlash('foo'));
        
        $s->migrate();
        
        $this->assertSame('bar',$s->get('foo'));
        $this->assertSame('bar',$s->getFlash('foo'));
        
        $s->restart();
        $s->invalidate();
        
        $this->assertSame(array(),$s->getAttributes());
        $this->assertSame(array(),$s->getFlashes());
    }
    
    public function testSerialize()
    {
        $storage = new SessionTestStorage();
        $options = array('foo'=>'bar');
        $s = new Session($storage,$options);
        
        $compare = serialize(array($storage, $options));
        
        $this->assertSame($compare, $s->serialize());
        
        $s->unserialize($compare);
        
        $_options = new \ReflectionProperty(get_class($s),'options');
        $_options->setAccessible(true);
        
        $_storage = new \ReflectionProperty(get_class($s),'storage');
        $_storage->setAccessible(true);
        
        $this->assertEquals($_options->getValue($s),$options,'options match');
        $this->assertEquals($_storage->getValue($s),$storage,'storage match');
    }
 
    public function testSave()
    {
        $storage = new SessionTestStorage();
        $options = array('foo'=>'bar');
        $s = new Session($storage,$options);
        $s->set('foo','bar');
        
        $s->save();
        $compare = array('_symfony2'=>array('_flash'=>array(),'_locale'=>'en','foo'=>'bar'));
        
        $this->assertSame($storage->attrs,$compare);
    }
        
    /**
     * @dataProvider provider
     */
    public function testLocale($s)
    {        
        $this->assertSame('en',$s->getLocale(),'default locale is en');
        
        $s->set('_locale','de');
        
        $this->assertSame('de',$s->getLocale(),'locale is de');

        $s->restart();
        $s->setLocale('fr');
        $this->assertSame('fr',$s->getLocale(),'locale is fr');
    }
    
    /**
     * @dataProvider provider
     */
    public function testGetId($s)
    {
        $this->assertSame('foo',$s->getId());
    }
    
    /**
     * @dataProvider provider
     */
    public function testStart($s)
    {
        $s->start();
        
        $this->assertSame('en',$s->getLocale());
        $this->assertSame(array(),$s->getFlashes());
        $this->assertSame(array('_flash'=>array(),'_locale'=>'en'),$s->getAttributes());

        $s->start();
        $this->assertSame('en',$s->getLocale());
    }
    
    
    public function provider()
    {
        $storage = new SessionTestStorage();
        $session = new SessionTestSession($storage); 
 
        return array(
            array($session)
        );
    }
}

class SessionTestSession extends Session
{
    /**
     * Little helper for simulating a fresh session.
     */
    public function restart()
    {
        $this->started = false;
    }
}

class SessionTestStorage implements SessionStorageInterface
{
   public $attrs;
   
   function start()
   {
   }
   
   function getId()
   {
       return 'foo';
   }

   function read($key)
   {
   }
   
   function remove($key)
   {
   }
   
   function write($key, $data)
   {
       $this->attrs[$key] = $data;
   }
   
   function regenerate($destroy = false)
   {
   }
}
