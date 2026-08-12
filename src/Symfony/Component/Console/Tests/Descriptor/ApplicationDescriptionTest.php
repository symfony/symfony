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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Descriptor\ApplicationDescription;
use Symfony\Component\Console\Output\OutputInterface;

final class ApplicationDescriptionTest extends TestCase
{
    #[DataProvider('getNamespacesProvider')]
    public function testGetNamespaces(array $expected, array $names)
    {
        $application = new TestApplication();
        foreach ($names as $name) {
            $application->addCommand(new Command($name));
        }

        $this->assertSame($expected, array_keys((new ApplicationDescription($application))->getNamespaces()));
    }

    public static function getNamespacesProvider()
    {
        return [
            [['_global'], ['foobar']],
            [['a', 'b'], ['b:foo', 'a:foo', 'b:bar']],
            [['_global', 22, 33, 'b', 'z'], ['z:foo', '1', '33:foo', 'b:foo', '22:foo:bar']],
        ];
    }

    public function testCommandsAreFilteredByListedAt()
    {
        $application = new Application();
        $always = (new Command('app:always'))->setCode(static fn () => 0);
        $verbose = (new Command('app:verbose'))->setCode(static fn () => 0)->setListedAt(OutputInterface::VERBOSITY_VERBOSE);
        $application->addCommand($always);
        $application->addCommand($verbose);
        $normal = new ApplicationDescription($application);
        $this->assertArrayHasKey('app:always', $normal->getCommands());
        $this->assertArrayNotHasKey('app:verbose', $normal->getCommands());
        $this->assertSame(OutputInterface::VERBOSITY_VERBOSE, $normal->getNextVerbosity());
        $raised = new ApplicationDescription($application, null, false, OutputInterface::VERBOSITY_VERBOSE);
        $this->assertArrayHasKey('app:verbose', $raised->getCommands());
        $this->assertNull($raised->getNextVerbosity());
    }
}

final class TestApplication extends Application
{
    protected function getDefaultCommands(): array
    {
        return [];
    }
}
