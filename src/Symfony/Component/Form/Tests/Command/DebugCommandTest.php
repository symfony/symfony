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
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Command\DebugCommand;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\ResolvedFormTypeFactory;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DebugCommandTest extends TestCase
{
    public function testDebugDefaults()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(array(), array('decorated' => false));

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertContains('Built-in form types', $tester->getDisplay());
    }

    public function testDebugDeprecatedDefaults()
    {
        $tester = $this->createCommandTester(array('Symfony\Component\Form\Tests\Console\Descriptor'), array(TextType::class, FooType::class));
        $ret = $tester->execute(array('--show-deprecated' => true), array('decorated' => false));

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertSame(<<<TXT

Built-in form types (Symfony\Component\Form\Extension\Core\Type)
----------------------------------------------------------------

 IntegerType

Service form types
------------------

 * Symfony\Component\Form\Tests\Command\FooType


TXT
        , $tester->getDisplay(true));
    }

    public function testDebugSingleFormType()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(array('class' => 'FormType'), array('decorated' => false));

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertContains('Symfony\Component\Form\Extension\Core\Type\FormType (Block prefix: "form")', $tester->getDisplay());
    }

    public function testDebugFormTypeOption()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(array('class' => 'FormType', 'option' => 'method'), array('decorated' => false));

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertContains('Symfony\Component\Form\Extension\Core\Type\FormType (method)', $tester->getDisplay());
    }

    /**
     * @expectedException \Symfony\Component\Console\Exception\InvalidArgumentException
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
    Symfony\Component\Form\Tests\Fixtures\Debug\A\AmbiguousType
    Symfony\Component\Form\Tests\Fixtures\Debug\B\AmbiguousType
TXT;

        if (method_exists($this, 'expectException')) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage($expectedMessage);
        } else {
            $this->setExpectedException(InvalidArgumentException::class, $expectedMessage);
        }

        $tester = $this->createCommandTester(array(
            'Symfony\Component\Form\Tests\Fixtures\Debug\A',
            'Symfony\Component\Form\Tests\Fixtures\Debug\B',
        ));

        $tester->execute(array('class' => 'AmbiguousType'), array('decorated' => false, 'interactive' => false));
    }

    public function testDebugAmbiguousFormTypeInteractive()
    {
        $tester = $this->createCommandTester(array(
            'Symfony\Component\Form\Tests\Fixtures\Debug\A',
            'Symfony\Component\Form\Tests\Fixtures\Debug\B',
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

    private function createCommandTester(array $namespaces = array('Symfony\Component\Form\Extension\Core\Type'), array $types = array())
    {
        $formRegistry = new FormRegistry(array(), new ResolvedFormTypeFactory());
        $command = new DebugCommand($formRegistry, $namespaces, $types);
        $application = new Application();
        $application->add($command);

        return new CommandTester($application->find('debug:form'));
    }
}

class FooType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired('foo');
        $resolver->setDefined('bar');
        $resolver->setDeprecated('bar');
        $resolver->setDefault('empty_data', function (Options $options) {
            $foo = $options['foo'];

            return function (FormInterface $form) use ($foo) {
                return $form->getConfig()->getCompound() ? array($foo) : $foo;
            };
        });
        $resolver->setAllowedTypes('foo', 'string');
        $resolver->setAllowedValues('foo', array('bar', 'baz'));
        $resolver->setNormalizer('foo', function (Options $options, $value) {
            return (string) $value;
        });
    }
}
