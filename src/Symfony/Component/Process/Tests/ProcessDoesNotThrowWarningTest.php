<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Process\Tests;

use Symfony\Component\Process\Process;

class ProcessDoesNotThrowWarningTest extends \PHPUnit_Framework_TestCase
{	
    public function testThatProcessDoesNotThrowWarningDuringRun(){
        @trigger_error('Test Error', E_USER_NOTICE);
        $process = $this->getProcess("php -r 'sleep(3)'");
        $process->run();
        $actualError = error_get_last();
        $this->assertEquals('Test Error', $actualError['message']); 
        $this->assertEquals(E_USER_NOTICE, $actualError['type']); 
    }
    protected function getProcess($commandline, $cwd = null, array $env = null, $stdin = null, $timeout = 60, array $options = array())
    {
        return new Process($commandline, $cwd, $env, $stdin, $timeout, $options);
    }
    
}

