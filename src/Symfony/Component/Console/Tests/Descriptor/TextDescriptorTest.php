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

use Symfony\Component\Console\Descriptor\TextDescriptor;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tests\Fixtures\DescriptorApplication2;
use Symfony\Component\Console\Tests\Fixtures\DescriptorApplicationMbString;
use Symfony\Component\Console\Tests\Fixtures\DescriptorCommandMbString;

class TextDescriptorTest extends AbstractDescriptorTestCase
{
    public static function getDescribeCommandTestData()
    {
        return self::getDescriptionTestData(array_merge(
            ObjectsProvider::getCommands(),
            ['command_mbstring' => new DescriptorCommandMbString()]
        ));
    }

    public static function getDescribeApplicationTestData()
    {
        return self::getDescriptionTestData(array_merge(
            ObjectsProvider::getApplications(),
            ['application_mbstring' => new DescriptorApplicationMbString()]
        ));
    }

    public function testDescribeInputArgumentWithMultibyteName()
    {
        $output = new BufferedOutput();
        (new TextDescriptor())->describe($output, new InputArgument('路径', InputArgument::REQUIRED), ['raw_output' => true]);

        $this->assertStringContainsString('路径', $output->fetch());
    }

    public function testDescribeApplicationWithFilteredNamespace()
    {
        $application = new DescriptorApplication2();

        $this->assertDescription(file_get_contents(__DIR__.'/../Fixtures/application_filtered_namespace.txt'), $application, ['namespace' => 'command4']);
    }

    protected function getDescriptor()
    {
        return new TextDescriptor();
    }

    protected static function getFormat()
    {
        return 'txt';
    }
}
