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
        $outputMock = $this->prophesize('Symfony\Component\Console\Output\OutputInterface');

        $this->setExpectedException('InvalidArgumentException');
        $descriptor->describe($outputMock->reveal(), new \stdClass(), ['format' => 'non-existing-format']);
    }
}
