<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Descriptor;

use Symfony\Component\Console\Descriptor\MarkdownDescriptor;

class DescriptorTest extends \PHPUnit_Framework_TestCase
{
    public function testDescribeOnlyAllowedObjects()
    {
        $descriptor = new MarkdownDescriptor();
        $output = $this->prophesize('Symfony\Component\Console\Output\OutputInterface');

        $this->setExpectedException('InvalidArgumentException');
        $descriptor->describe($output->reveal(), new \stdClass());
    }
}
