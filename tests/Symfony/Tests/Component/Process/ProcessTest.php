<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Process;

use Symfony\Component\Process\Process;

/**
 * @author Robert Schönthal <seroscho@googlemail.com>
 */
class ProcessTest extends \PHPUnit_Framework_TestCase
{
    /**
     * tests getter/setter
     * 
     * @dataProvider methodProvider
     */
    public function testDefaultGetterSetter($fn)
    {
        $p = new Process('php');
        
        $setter = 'set'.$fn;
        $getter = 'get'.$fn;

        $this->assertNull($p->$setter(array('foo')));

        $this->assertSame(array('foo'), $p->$getter(array('foo')));
    }
    
    /**
     * tests results from sub processes
     * 
     * @dataProvider codeProvider
     */
    public function testProcessResponses($expected, $getter, $code)
    {
        $p = new Process(sprintf('php -r "%s"', $code));
        $p->run();
        
        $this->assertSame($expected, $p->$getter());
    }
    
    public function codeProvider()
    {
        return array(
            //expected output / getter / code to execute
            //array(1,'getExitCode','exit(1);'),
            //array(true,'isSuccessful','exit();'),
            array('output', 'getOutput', 'echo \"output\";'),
        );
    }
    
    /**
     * provides default method names for simple getter/setter
     */
    public function methodProvider()
    {
        $defaults = array(
            array('CommandLine'),
            array('Timeout'),
            array('WorkingDirectory'),
            array('Env'),
            array('Stdin'),
            array('Options')
        );
        
        return $defaults;
    }
}
