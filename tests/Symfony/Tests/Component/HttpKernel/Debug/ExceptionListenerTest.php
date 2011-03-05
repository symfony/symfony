<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpKernel\Debug;

use Symfony\Component\HttpKernel\Debug\ExceptionListener;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Debug\ErrorException;
use Symfony\Tests\Component\HttpKernel\Logger;

/**
 * ExceptionListenerTest
 *
 * @author Robert Schönthal <seroscho@googlemail.com>
 */
class ExceptionListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testConstruct()
    {
        $logger = new TestLogger();
        $l = new ExceptionListener('foo', $logger);
        
        $_logger = new \ReflectionProperty(get_class($l),'logger');
        $_logger->setAccessible(true);
        $_controller = new \ReflectionProperty(get_class($l),'controller');
        $_controller->setAccessible(true);
        
        $this->assertSame($logger,$_logger->getValue($l));
        $this->assertSame('foo',$_controller->getValue($l));
    }
    
    /**
     * @dataProvider provider
     */
    public function testHandleWithoutLogger($event,$event2)
    {
        //store the current error_log, and set the new one to dev/null
        $error_log = ini_get('error_log');
        ini_set('error_log','/dev/null');
        
        $l = new ExceptionListener('foo');

        $this->assertEquals('foo', $l->handle($event));
        
        try{
            $response = $l->handle($event2);
        }catch(\Exception $e){
            $this->assertSame('foo',$e->getMessage());
        }
        
        //restore the old error_log
        ini_set('error_log',$error_log);
    }
    
    /**
     * @dataProvider provider
     */
    public function testHandleWithLogger($event, $event2)
    {
        $logger = new TestLogger();
        
        $l = new ExceptionListener('foo',$logger);
        
        $this->assertSame('foo', $l->handle($event));
        
        try{
            $response = $l->handle($event2);
        }catch(\Exception $e){
            $this->assertSame('foo',$e->getMessage());
        }
        
        $this->assertEquals(3,$logger->countErrors());
        $this->assertEquals(3,count($logger->getLogs('err')));
    }
    
    public function provider()
    {
        $args = array('exception'=>new ErrorException('foo'),'request'=>new Request());
        
        $event = new Event(new Subject(),'bar',$args);
        $event2 = new Event(new SubjectException(),'bar',$args);

        return array(
            array($event,$event2)
        );
    }
    
}

class TestLogger extends Logger implements DebugLoggerInterface
{
    public function countErrors()
    {
        return count($this->logs['err']);
    }
    
    public function getDebugLogger()
    {
        return new static();
    }
}

class Subject
{
    public function handle()
    {
        return 'foo';
    }
 
}

class SubjectException
{
    public function handle()
    {
        throw new \Exception('bar');
    }
}