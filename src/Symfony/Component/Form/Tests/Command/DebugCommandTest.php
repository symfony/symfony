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
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Form\Command\DebugCommand;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\ResolvedFormTypeInterface;

class DebugCommandTest extends TestCase
{
    public function testDebugSingleFormType()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(array('class' => 'FormType'), array('decorated' => false));

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertContains('Symfony\Component\Form\Extension\Core\Type\FormType (Block prefix: "form")', $tester->getDisplay());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testDebugInvalidFormType()
    {
        $this->createCommandTester()->execute(array('class' => 'test'));
    }

    /**
     * @return CommandTester
     */
    private function createCommandTester()
    {
        $resolvedFormType = $this->getMockBuilder(ResolvedFormTypeInterface::class)->getMock();
        $resolvedFormType
            ->expects($this->any())
            ->method('getParent')
            ->willReturn(null)
        ;
        $resolvedFormType
            ->expects($this->any())
            ->method('getInnerType')
            ->willReturn(new FormType())
        ;
        $resolvedFormType
            ->expects($this->any())
            ->method('getTypeExtensions')
            ->willReturn(array())
        ;

        $formRegistry = $this->getMockBuilder(FormRegistryInterface::class)->getMock();
        $formRegistry
            ->expects($this->any())
            ->method('getType')
            ->will($this->returnValue($resolvedFormType))
        ;

        $command = new DebugCommand($formRegistry);
        $application = new Application();
        $application->add($command);

        return new CommandTester($application->find('debug:form'));
    }
}
