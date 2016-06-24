<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Helper;

use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Output\OutputInterface;

class DescriptorHelperTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException        \Symfony\Component\Console\Exception\InvalidArgumentException
     * @expectedExceptionMessage Unsupported format "invalid".
     */
    public function testDescribeWithInvalidFormat()
    {
        $descriptor = new DescriptorHelper();
        $descriptor->describe($this->getMock(OutputInterface::class), null, array('format' => 'invalid'));
    }

    public function testGetName()
    {
        $descriptor = new DescriptorHelper();

        $this->assertSame('descriptor', $descriptor->getName());
    }
}
