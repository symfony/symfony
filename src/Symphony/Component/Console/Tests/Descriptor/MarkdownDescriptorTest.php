<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Console\Tests\Descriptor;

use Symphony\Component\Console\Descriptor\MarkdownDescriptor;
use Symphony\Component\Console\Tests\Fixtures\DescriptorApplicationMbString;
use Symphony\Component\Console\Tests\Fixtures\DescriptorCommandMbString;

class MarkdownDescriptorTest extends AbstractDescriptorTest
{
    public function getDescribeCommandTestData()
    {
        return $this->getDescriptionTestData(array_merge(
            ObjectsProvider::getCommands(),
            array('command_mbstring' => new DescriptorCommandMbString())
        ));
    }

    public function getDescribeApplicationTestData()
    {
        return $this->getDescriptionTestData(array_merge(
            ObjectsProvider::getApplications(),
            array('application_mbstring' => new DescriptorApplicationMbString())
        ));
    }

    protected function getDescriptor()
    {
        return new MarkdownDescriptor();
    }

    protected function getFormat()
    {
        return 'md';
    }
}
