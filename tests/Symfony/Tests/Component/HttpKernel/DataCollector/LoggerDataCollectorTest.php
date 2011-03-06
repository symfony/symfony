<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpKernel\DataCollector;

use Symfony\Component\HttpKernel\DataCollector\LoggerDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Symfony\Tests\Component\HttpKernel\Logger;

class LoggerDataCollectorTest extends \PHPUnit_Framework_TestCase
{
    public function testCollect()
    {
        $c = new LoggerDataCollector(new TestLogger());
        
        $c->collect(new Request(), new Response());
        
        $this->assertSame('logger',$c->getName());
        $this->assertSame(1337,$c->countErrors());
        $this->assertSame(array('foo'),$c->getLogs());
    }
    
}

class TestLogger extends Logger implements DebugLoggerInterface
{
    public function countErrors()
    {
        return 1337;
    }
    
    public function getDebugLogger()
    {
        return new static();
    }
    
    public function getLogs($priority = false)
    {
        return array('foo');
    }
}

