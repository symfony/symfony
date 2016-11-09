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

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Output\OutputInterface;

class DescriptorHelperTest extends \PHPUnit_Framework_TestCase
{
    public function testGetName()
    {
        $descriptor = new DescriptorHelper();
        $this->assertSame('descriptor', $descriptor->getName());
    }

    public function testDescribeNonExistingFormat()
    {
        $descriptor = new DescriptorHelper();
        $outputMock = $this->prophesize(OutputInterface::class);

        $this->setExpectedException(InvalidArgumentException::class);
        $descriptor->describe($outputMock->reveal(), new \stdClass(), ['format' => 'non-existing-format']);
    }
}
