<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Console\Application;
use Symphony\Component\Console\Exception\InvalidArgumentException;
use Symphony\Component\Console\Tester\CommandTester;
use Symphony\Component\Form\Command\DebugCommand;
use Symphony\Component\Form\FormRegistry;
use Symphony\Component\Form\ResolvedFormTypeFactory;

class DebugCommandTest extends TestCase
{
    public function testDebugDefaults()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(array(), array('decorated' => false));

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertContains('Built-in form types', $tester->getDisplay());
    }

    public function testDebugSingleFormType()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(array('class' => 'FormType'), array('decorated' => false));

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertContains('Symphony\Component\Form\Extension\Core\Type\FormType (Block prefix: "form")', $tester->getDisplay());
    }

    public function testDebugFormTypeOption()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(array('class' => 'FormType', 'option' => 'method'), array('decorated' => false));

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertContains('Symphony\Component\Form\Extension\Core\Type\FormType (method)', $tester->getDisplay());
    }

    /**
     * @expectedException \Symphony\Component\Console\Exception\InvalidArgumentException
     * @expectedExceptionMessage Could not find type "NonExistentType"
     */
    public function testDebugSingleFormTypeNotFound()
    {
        $tester = $this->createCommandTester();
        $tester->execute(array('class' => 'NonExistentType'), array('decorated' => false, 'interactive' => false));
    }

    public function testDebugAmbiguousFormType()
    {
        $expectedMessage = <<<TXT
The type "AmbiguousType" is ambiguous.

Did you mean one of these?
    Symphony\Component\Form\Tests\Fixtures\Debug\A\AmbiguousType
    Symphony\Component\Form\Tests\Fixtures\Debug\B\AmbiguousType
TXT;

        if (method_exists($this, 'expectException')) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage($expectedMessage);
        } else {
            $this->setExpectedException(InvalidArgumentException::class, $expectedMessage);
        }

        $tester = $this->createCommandTester(array(
            'Symphony\Component\Form\Tests\Fixtures\Debug\A',
            'Symphony\Component\Form\Tests\Fixtures\Debug\B',
        ));

        $tester->execute(array('class' => 'AmbiguousType'), array('decorated' => false, 'interactive' => false));
    }

    public function testDebugAmbiguousFormTypeInteractive()
    {
        $tester = $this->createCommandTester(array(
            'Symphony\Component\Form\Tests\Fixtures\Debug\A',
            'Symphony\Component\Form\Tests\Fixtures\Debug\B',
        ));

        $tester->setInputs(array(0));
        $tester->execute(array('class' => 'AmbiguousType'), array('decorated' => false, 'interactive' => true));

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

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testDebugInvalidFormType()
    {
        $this->createCommandTester()->execute(array('class' => 'test'));
    }

    private function createCommandTester(array $namespaces = null)
    {
        $formRegistry = new FormRegistry(array(), new ResolvedFormTypeFactory());
        $command = null === $namespaces ? new DebugCommand($formRegistry) : new DebugCommand($formRegistry, $namespaces);
        $application = new Application();
        $application->add($command);

        return new CommandTester($application->find('debug:form'));
    }
}
