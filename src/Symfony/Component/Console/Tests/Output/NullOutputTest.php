<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Output;

use Symfony\Component\Console\Output\NullOutput;

class NullOutputTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $output = new NullOutput();
        $output->write('foo');
        $this->assertTrue(true, '->write() does nothing'); // FIXME
    }
}
