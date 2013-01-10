<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\OutputStream;

use Symfony\Component\HttpFoundation\OutputStream\StreamOutputStream;

class OutputStreamTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $fp = fopen('php://memory', 'rw');
        $output = new StreamOutputStream($fp);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructorWithInvalidStream()
    {
        $fp = 'foobar';
        $output = new StreamOutputStream($fp);
    }

    public function testWrite()
    {
        $fp = fopen('php://memory', 'rw');
        $output = new StreamOutputStream($fp);

        $output->write('sample output');
        rewind($fp);
        $this->assertEquals('sample output', fread($fp, 50));
    }

    public function testClose()
    {
        $fp = fopen('php://memory', 'rw');
        $output = new StreamOutputStream($fp);

        $output->close();
        try {
            fread($fp, 1);
            $this->fail();
        } catch (\PHPUnit_Framework_Error_Warning $e) {
        }
    }
}
