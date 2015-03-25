<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Command;

use Symfony\Component\Console\Command\CommandConfiguration;
use Symfony\Component\Console\Input\InputDefinition;

class CommandConfigurationTest  extends \PHPUnit_Framework_TestCase
{
    public function testImmutablity()
    {
        $configuration = new CommandConfiguration('foo:bar');

        $newConfiguration = $configuration->withName('foo:baz');
        $this->assertNotSame($configuration, $newConfiguration, '->withName() does not mutate original configuration');

        $configuration = $newConfiguration;
        $newConfiguration = $configuration->withAliases(array('bar:foo'));
        $this->assertNotSame($configuration, $newConfiguration, '->withAliases() does not mutate original configuration');

        $configuration = $newConfiguration;
        $newConfiguration = $configuration->withDefinition(new InputDefinition());
        $this->assertNotSame($configuration, $newConfiguration, '->withDefinition() does not mutate original configuration');

        $configuration = $newConfiguration;
        $newConfiguration = $configuration->withProcessTitle('title');
        $this->assertNotSame($configuration, $newConfiguration, '->withProcessTitle() does not mutate original configuration');

        $configuration = $newConfiguration;
        $newConfiguration = $configuration->withHelp('help');
        $this->assertNotSame($configuration, $newConfiguration, '->withHelp() does not mutate original configuration');

        $configuration = $newConfiguration;
        $newConfiguration = $configuration->withDescription('description');
        $this->assertNotSame($configuration, $newConfiguration, '->withDescription() does not mutate original configuration');
    }

    public function testFactories()
    {
        $configuration = new CommandConfiguration('foo:bar');

        $this->assertSame('foo:bar', $configuration->getName(), '->getName() returns correct command name');
        $this->assertSame('bar:foo', $configuration->withName('bar:foo')->getName(), '->withName() returns configuration with new name');

        $this->assertSame(array(), $configuration->getAliases());
        $this->assertSame(array('bar:baz'), $configuration->withAliases(array('bar:baz'))->getAliases(), '->withAliases() returns configuration with new aliases');

        $this->assertNull($configuration->getProcessTitle());
        $this->assertSame('title', $configuration->withProcessTitle('title')->getProcessTitle(), '->getProcessTitle() returns configuration with new title');

        $this->assertNull($configuration->getHelp());
        $this->assertSame('help', $configuration->withHelp('help')->getHelp(), '->withHelp() returns configuration with new help');

        $this->assertNull($configuration->getDescription());
        $this->assertSame('description', $configuration->withDescription('description')->getDescription(), '->withDescription() returns configuration with new description');
    }

    /**
     * @dataProvider provideInvalidCommandNames
     */
    public function testInvalidCommandNames($name)
    {
        $this->setExpectedException('InvalidArgumentException', sprintf('Command name "%s" is invalid.', $name));

        new CommandConfiguration($name);
    }

    public function provideInvalidCommandNames()
    {
        return array(
            array(''),
            array('foo:'),
        );
    }
}
