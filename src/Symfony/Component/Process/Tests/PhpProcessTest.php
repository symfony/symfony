<?php

namespace Symfony\Component\Process\Tests;

use Symfony\Component\Process\PhpProcess;

class PhpProcessTest extends \PHPUnit_Framework_TestCase
{

    public function testNonBlockingWorks()
    {
        $expected = 'hello world!';
        $process = new PhpProcess(<<<PHP
<?php echo '$expected';
PHP
        );
        $process->start();
        $process->wait();
        $this->assertEquals($expected, $process->getOutput());
    }
}
