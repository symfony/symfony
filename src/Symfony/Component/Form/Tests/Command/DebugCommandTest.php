<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Form\Command\DebugCommand;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\ResolvedFormTypeFactory;

class DebugCommandTest extends TestCase
{
    public function testDebugDefaults()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute([], ['decorated' => false]);

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('Built-in form types', $tester->getDisplay());
    }

    public function testDebugSingleFormType()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['class' => 'FormType'], ['decorated' => false]);

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('Symfony\Component\Form\Extension\Core\Type\FormType (Block prefix: "form")', $tester->getDisplay());
    }

    public function testDebugFormTypeOption()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['class' => 'FormType', 'option' => 'method'], ['decorated' => false]);

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('Symfony\Component\Form\Extension\Core\Type\FormType (method)', $tester->getDisplay());
    }

    public function testDebugSingleFormTypeNotFound()
    {
        $this->expectException('Symfony\Component\Console\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Could not find type "NonExistentType"');
        $tester = $this->createCommandTester();
        $tester->execute(['class' => 'NonExistentType'], ['decorated' => false, 'interactive' => false]);
    }

    public function testDebugAmbiguousFormType()
    {
        $expectedMessage = <<<TXT
The type "AmbiguousType" is ambiguous.

Did you mean one of these?
    Symfony\Component\Form\Tests\Fixtures\Debug\A\AmbiguousType
    Symfony\Component\Form\Tests\Fixtures\Debug\B\AmbiguousType
TXT;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        $tester = $this->createCommandTester([
            'Symfony\Component\Form\Tests\Fixtures\Debug\A',
            'Symfony\Component\Form\Tests\Fixtures\Debug\B',
        ]);

        $tester->execute(['class' => 'AmbiguousType'], ['decorated' => false, 'interactive' => false]);
    }

    public function testDebugAmbiguousFormTypeInteractive()
    {
        $tester = $this->createCommandTester([
            'Symfony\Component\Form\Tests\Fixtures\Debug\A',
            'Symfony\Component\Form\Tests\Fixtures\Debug\B',
        ]);

        $tester->setInputs([0]);
        $tester->execute(['class' => 'AmbiguousType'], ['decorated' => false, 'interactive' => true]);

        $this->assertEquals(0, $tester->getStatusCode(), 'Returns 0 in case of success');
        $output = $tester->getDisplay(true);
        $this->assertStringMatchesFormat(<<<TXT

 The type "AmbiguousType" is ambiguous.

Select one of the following form types to display its information: [%A\A\AmbiguousType]:
  [0] %A\A\AmbiguousType
  [1] %A\B\AmbiguousType
%A
%A\A\AmbiguousType (Block prefix: "ambiguous")
%A
TXT
        , $output);
    }

    public function testDebugInvalidFormType()
    {
        $this->expectException('InvalidArgumentException');
        $this->createCommandTester()->execute(['class' => 'test']);
    }

    private function createCommandTester(array $namespaces = null)
    {
        $formRegistry = new FormRegistry([], new ResolvedFormTypeFactory());
        $command = null === $namespaces ? new DebugCommand($formRegistry) : new DebugCommand($formRegistry, $namespaces);
        $application = new Application();
        $application->add($command);

        return new CommandTester($application->find('debug:form'));
    }
}
