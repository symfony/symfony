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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

abstract class AbstractDescriptorTest extends TestCase
{
    /** @dataProvider getDescribeInputArgumentTestData */
    public function testDescribeInputArgument(InputArgument $argument, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $argument);
    }

    /** @dataProvider getDescribeInputOptionTestData */
    public function testDescribeInputOption(InputOption $option, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $option);
    }

    /** @dataProvider getDescribeInputDefinitionTestData */
    public function testDescribeInputDefinition(InputDefinition $definition, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $definition);
    }

    /** @dataProvider getDescribeCommandTestData */
    public function testDescribeCommand(Command $command, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $command);
    }

    /** @dataProvider getDescribeApplicationTestData */
    public function testDescribeApplication(Application $application, $expectedDescription)
    {
        $this->assertDescription($expectedDescription, $application);
    }

    public function getDescribeInputArgumentTestData()
    {
        return $this->getDescriptionTestData(ObjectsProvider::getInputArguments());
    }

    public function getDescribeInputOptionTestData()
    {
        return $this->getDescriptionTestData(ObjectsProvider::getInputOptions());
    }

    public function getDescribeInputDefinitionTestData()
    {
        return $this->getDescriptionTestData(ObjectsProvider::getInputDefinitions());
    }

    public function getDescribeCommandTestData()
    {
        return $this->getDescriptionTestData(ObjectsProvider::getCommands());
    }

    public function getDescribeApplicationTestData()
    {
        return $this->getDescriptionTestData(ObjectsProvider::getApplications());
    }

    abstract protected function getDescriptor();

    abstract protected function getFormat();

    protected function getDescriptionTestData(array $objects)
    {
        $data = [];
        foreach ($objects as $name => $object) {
            $description = file_get_contents(sprintf('%s/../Fixtures/%s.%s', __DIR__, $name, $this->getFormat()));
            $data[] = [$object, $description];
        }

        return $data;
    }

    protected function assertDescription($expectedDescription, $describedObject, array $options = [])
    {
        $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);
        $this->getDescriptor()->describe($output, $describedObject, $options + ['raw_output' => true]);
        $this->assertEquals($this->normalizeOutput($expectedDescription), $this->normalizeOutput($output->fetch()));
    }

    protected function normalizeOutput(string $output)
    {
        $output = str_replace(['%%PHP_SELF%%', '%%PHP_SELF_FULL%%', '%%COMMAND_NAME%%', '%%SHELL%%'], [$_SERVER['PHP_SELF'], realpath($_SERVER['PHP_SELF']), basename($_SERVER['PHP_SELF']), basename($_SERVER['SHELL'] ?? '')], $output);

        return trim(str_replace(\PHP_EOL, "\n", $output));
    }
}
