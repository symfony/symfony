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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Descriptor\TextDescriptor;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
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

    public function testOptionDescriptionWrapsAtTerminalWidth()
    {
        $option = new InputOption('verbose', 'v', InputOption::VALUE_NONE, 'Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug');

        $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);
        $this->getDescriptor()->describe($output, $option, ['raw_output' => true, 'terminal_width' => 80]);
        $result = strip_tags($output->fetch());

        $this->assertStringContainsString('-v, --verbose', $result);
        $this->assertGreaterThan(1, \count(explode("\n", trim($result))), 'Long description should wrap to multiple lines');
        foreach (explode("\n", trim($result)) as $line) {
            $this->assertLessThanOrEqual(80, \strlen($line), \sprintf('Line exceeds terminal width: "%s"', $line));
        }
    }

    public function testOptionDescriptionFallbackOnNarrowTerminal()
    {
        $option = new InputOption('update-with-all-dependencies', null, InputOption::VALUE_NONE, 'Allows all inherited dependencies to be updated, including those that are root requirements.');

        $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);
        $this->getDescriptor()->describe($output, $option, ['raw_output' => true, 'terminal_width' => 50]);
        $result = strip_tags($output->fetch());
        $lines = explode("\n", trim($result));

        $this->assertStringContainsString('--update-with-all-dependencies', $lines[0]);
        $this->assertStringNotContainsString('Allows', $lines[0], 'Description should be on the next line when terminal is narrow');
        $this->assertStringContainsString('Allows', $lines[1]);
        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(50, \strlen($line), \sprintf('Line exceeds terminal width: "%s"', $line));
        }
    }

    public function testOptionDescriptionWithDefaultWraps()
    {
        $option = new InputOption('format', 'f', InputOption::VALUE_REQUIRED, 'The output format for this very long description that should wrap', 'txt');

        $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);
        $this->getDescriptor()->describe($output, $option, ['raw_output' => true, 'terminal_width' => 60]);
        $result = strip_tags($output->fetch());

        $lines = explode("\n", trim($result));
        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(60, \strlen($line), \sprintf('Line exceeds terminal width: "%s"', $line));
        }
        $this->assertStringContainsString('[default: "txt"]', $result);
    }

    public function testHelpTextWrapsAtTerminalWidth()
    {
        $command = new Command('test:wrap');
        $command->setDescription('Test command');
        $command->setHelp('This is a very long help text that should be automatically wrapped when the terminal width is not wide enough to display it on a single line.');

        $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);
        $this->getDescriptor()->describe($output, $command, ['raw_output' => true, 'terminal_width' => 60]);
        $result = strip_tags($output->fetch());

        $this->assertStringContainsString("Help:\n", $result);
        $helpStart = strpos($result, "Help:\n") + 6;
        $helpSection = substr($result, $helpStart);
        foreach (explode("\n", trim($helpSection)) as $line) {
            $this->assertLessThanOrEqual(60, \strlen($line), \sprintf('Help line exceeds terminal width: "%s"', $line));
        }
    }

    public function testCommandListDescriptionWraps()
    {
        $application = new DescriptorApplication2();
        $application->find('completion')->setHelp('');

        $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);
        $this->getDescriptor()->describe($output, $application, ['raw_output' => true, 'terminal_width' => 60]);
        $result = strip_tags($output->fetch());

        foreach (explode("\n", $result) as $line) {
            $this->assertLessThanOrEqual(60, \strlen($line), \sprintf('Line exceeds terminal width: "%s"', $line));
        }
    }

    public function testNoWrappingWhenContentFits()
    {
        $option = new InputOption('quiet', 'q', InputOption::VALUE_NONE, 'Suppress output');

        $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);
        $this->getDescriptor()->describe($output, $option, ['raw_output' => true, 'terminal_width' => 80]);
        $result = trim(strip_tags($output->fetch()));

        $this->assertStringNotContainsString("\n", $result);
    }

    protected function getDescriptor()
    {
        return new TextDescriptor();
    }

    protected static function getFormat()
    {
        return 'txt';
    }

    public function testWrappingMeasuresVisibleWidthNotBytes()
    {
        $output = new BufferedOutput();
        $option = new InputOption('name', null, InputOption::VALUE_REQUIRED, 'Précède les caractères accentués événement téléphone préférée');
        (new TextDescriptor())->describe($output, $option, ['terminal_width' => 60, 'raw_output' => true]);

        $lines = explode("\n", rtrim($output->fetch()));
        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(60, Helper::width(Helper::removeDecoration(new OutputFormatter(), $line)));
        }
        // wrapping on bytes would count the accents twice and break one word earlier
        $this->assertStringEndsWith('accentués', Helper::removeDecoration(new OutputFormatter(), $lines[0]));
    }

    public function testWrappingDoesNotCountFormatterTags()
    {
        $output = new BufferedOutput();
        $option = new InputOption('flag', null, InputOption::VALUE_REQUIRED, 'Chooses the mode used for processing', 'super-long-default-value');
        (new TextDescriptor())->describe($output, $option, ['terminal_width' => 60, 'raw_output' => true]);

        $lines = explode("\n", rtrim($output->fetch()));
        $this->assertStringEndsWith('processing', Helper::removeDecoration(new OutputFormatter(), $lines[0]));
        $this->assertStringContainsString('[default: "super-long-default-value"]', $lines[1]);
    }

    public function testWrappingOnWideTerminal()
    {
        $output = new BufferedOutput();
        $option = new InputOption('name', null, InputOption::VALUE_REQUIRED, str_repeat('long description ', 30));
        (new TextDescriptor())->describe($output, $option, ['terminal_width' => 200, 'raw_output' => true]);

        $lines = explode("\n", rtrim($output->fetch()));
        $this->assertCount(3, $lines);
        $this->assertStringContainsString('long description', $lines[0]);
        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(200, Helper::width(Helper::removeDecoration(new OutputFormatter(), $line)));
        }
    }

    public function testUnboundedOutputWhenNotATty()
    {
        $output = new BufferedOutput();
        $option = new InputOption('name', null, InputOption::VALUE_REQUIRED, str_repeat('long description ', 30));
        (new TextDescriptor())->describe($output, $option, ['raw_output' => true]);

        $this->assertCount(1, explode("\n", rtrim($output->fetch())));
    }
}
