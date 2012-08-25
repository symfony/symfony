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
     * @dataProvider responsesCodeProvider
     */
    public function testProcessResponses($expected, $getter, $code)
    {
        $p = new Process(sprintf('php -r %s', escapeshellarg($code)));
        $p->run();

        $this->assertSame($expected, $p->$getter());
    }

    /**
     * tests results from sub processes
     *
     * @dataProvider pipesCodeProvider
     */
    public function testProcessPipes($expected, $code)
    {
        if (strpos(PHP_OS, "WIN") === 0) {
            $this->markTestSkipped('Test hangs on Windows & PHP due to https://bugs.php.net/bug.php?id=60120 and https://bugs.php.net/bug.php?id=51800');
        }

        $p = new Process(sprintf('php -r %s', escapeshellarg($code)));
        $p->setStdin($expected);
        $p->run();

        $this->assertSame($expected, $p->getOutput());
        $this->assertSame($expected, $p->getErrorOutput());
        $this->assertSame(0, $p->getExitCode());
    }

    public function responsesCodeProvider()
    {
        return array(
            //expected output / getter / code to execute
            //array(1,'getExitCode','exit(1);'),
            //array(true,'isSuccessful','exit();'),
            array('output', 'getOutput', 'echo \'output\';'),
        );
    }

    public function pipesCodeProvider()
    {
        $variations = array(
            'fwrite(STDOUT, $in = file_get_contents(\'php://stdin\')); fwrite(STDERR, $in);',
            'include \'' . __DIR__ . '/ProcessTestHelper.php\';',
        );
        $baseData = str_repeat('*', 1024);

        $codes = array();
        foreach (array(1, 16, 64, 1024, 4096) as $size) {
            $data = str_repeat($baseData, $size) . '!';
            foreach ($variations as $code) {
                $codes[] = array($data, $code);
            }
        }

        return $codes;
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
