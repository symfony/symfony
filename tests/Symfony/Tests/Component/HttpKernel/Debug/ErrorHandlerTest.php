<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpKernel\Debug;

use Symfony\Component\HttpKernel\Debug\ErrorHandler;
use Symfony\Component\HttpKernel\Debug\ErrorException;

/**
 * ErrorHandlerTest
 *
 * @author Robert Schönthal <seroscho@googlemail.com>
 */
class ErrorHandlerTest extends \PHPUnit_Framework_TestCase
{
    
    public function testConstruct()
    {
        $e = new ErrorHandler(3);
        
        $this->assertInstanceOf('Symfony\Component\HttpKernel\Debug\ErrorHandler',$e);
        
        $level = new \ReflectionProperty(get_class($e),'level');
        $level->setAccessible(true);
        
        $this->assertEquals(3,$level->getValue($e));
    }
    
    public function testRegister()
    {
        $e = new ErrorHandler(3);
        $e = $this->getMock(get_class($e), array('handle'));
        $e->expects($this->once())->method('handle');
        
        $e->register();
        
        try{
            trigger_error('foo');
        }catch(\Exception $e){
        }
    }

    public function testHandle()
    {
        $e = new ErrorHandler(0);
        $this->assertFalse($e->handle(0,'foo','foo.php',12,'foo'));
        
        $e = new ErrorHandler(3);
        $this->assertFalse($e->handle(4,'foo','foo.php',12,'foo'));
        
        $e = new ErrorHandler(3);
        try{
            $e->handle(1, 'foo', 'foo.php', 12,'foo');
        }catch(\ErrorException $e){
            $this->assertSame('1: foo in foo.php line 12',$e->getMessage());
        }
    }

}
