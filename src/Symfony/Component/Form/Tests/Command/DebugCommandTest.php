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
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Command\DebugCommand;
use Symfony\Component\Form\Extension\Core\CoreExtension;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\ResolvedFormTypeFactory;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DebugCommandTest extends TestCase
{
    public function testDebugDefaults()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute([], ['decorated' => false]);

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('Built-in form types', $tester->getDisplay());
    }

    public function testDebugDeprecatedDefaults()
    {
        $tester = $this->createCommandTester(['Symfony\Component\Form\Tests\Console\Descriptor'], [TextType::class, FooType::class]);
        $ret = $tester->execute(['--show-deprecated' => true], ['decorated' => false]);

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertSame(<<<TXT

Service form types
------------------

 * Symfony\Component\Form\Tests\Command\FooType


TXT
            , $tester->getDisplay(true));
    }

    public function testDebugSingleFormType()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['class' => 'FormType'], ['decorated' => false]);

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('Symfony\Component\Form\Extension\Core\Type\FormType (Block prefix: "form")', $tester->getDisplay());
    }

    public function testDebugDateTimeType()
    {
        $tester = $this->createCommandTester();
        $tester->execute(['class' => 'DateTime'], ['decorated' => false, 'interactive' => false]);

        $tester->assertCommandIsSuccessful('Returns 0 in case of success');
        $this->assertStringContainsString('Symfony\Component\Form\Extension\Core\Type\DateTimeType (Block prefix: "datetime")', $tester->getDisplay());
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
        $this->expectException(InvalidArgumentException::class);
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

        $tester->assertCommandIsSuccessful('Returns 0 in case of success');
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
        $this->expectException(\InvalidArgumentException::class);
        $this->createCommandTester()->execute(['class' => 'test']);
    }

    public function testDebugCustomFormTypeOption()
    {
        $tester = $this->createCommandTester([], [FooType::class]);
        $ret = $tester->execute(['class' => FooType::class, 'option' => 'foo'], ['decorated' => false]);

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertStringMatchesFormat(<<<'TXT'

Symfony\Component\Form\Tests\Command\FooType (foo)
==================================================

 ---------------- -----------%s
  Info             "Info"    %s
 ---------------- -----------%s
  Required         true      %s
 ---------------- -----------%s
  Default          -         %s
 ---------------- -----------%s
  Allowed types    [         %s
                     "string"%s
                   ]         %s
 ---------------- -----------%s
  Allowed values   [         %s
                     "bar",  %s
                     "baz"   %s
                   ]         %s
 ---------------- -----------%s
  Normalizers      [         %s
                     Closure(%s
                       class:%s
                       this: %s
                       file: %s
                       line: %s
                     }       %s
                   ]         %s
 ---------------- -----------%s

TXT
            , $tester->getDisplay(true));
    }

    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $formRegistry = new FormRegistry([], new ResolvedFormTypeFactory());
        $command = new DebugCommand($formRegistry);
        $application = new Application();
        $application->add($command);
        $tester = new CommandCompletionTester($application->get('debug:form'));
        $this->assertSame($expectedSuggestions, $tester->complete($input));
    }

    public static function provideCompletionSuggestions(): iterable
    {
        yield 'option --format' => [
            ['--format', ''],
            ['txt', 'json'],
        ];

        yield 'form_type' => [
            [''],
            self::getCoreTypes(),
        ];

        yield 'option for FQCN' => [
            ['Symfony\\Component\\Form\\Extension\\Core\\Type\\ButtonType', ''],
            [
                'block_name',
                'block_prefix',
                'disabled',
                'label',
                'label_format',
                'row_attr',
                'label_html',
                'label_translation_parameters',
                'attr_translation_parameters',
                'attr',
                'translation_domain',
                'auto_initialize',
                'priority',
                'form_attr',
            ],
        ];

        yield 'option for short name' => [
            ['ButtonType', ''],
            [
                'block_name',
                'block_prefix',
                'disabled',
                'label',
                'label_format',
                'row_attr',
                'label_html',
                'label_translation_parameters',
                'attr_translation_parameters',
                'attr',
                'translation_domain',
                'auto_initialize',
                'priority',
                'form_attr',
            ],
        ];

        yield 'option for ambiguous form type' => [
            ['Type', ''],
            [],
        ];

        yield 'option for invalid form type' => [
            ['NotExistingFormType', ''],
            [],
        ];
    }

    private static function getCoreTypes(): array
    {
        $coreExtension = new CoreExtension();
        $loadTypesRefMethod = (new \ReflectionObject($coreExtension))->getMethod('loadTypes');
        $coreTypes = $loadTypesRefMethod->invoke($coreExtension);
        $coreTypes = array_map(fn (FormTypeInterface $type) => $type::class, $coreTypes);
        sort($coreTypes);

        return $coreTypes;
    }

    private function createCommandTester(array $namespaces = ['Symfony\Component\Form\Extension\Core\Type'], array $types = [])
    {
        $formRegistry = new FormRegistry([], new ResolvedFormTypeFactory());
        $command = new DebugCommand($formRegistry, $namespaces, $types);
        $application = new Application();
        $application->add($command);

        return new CommandTester($application->find('debug:form'));
    }
}

class FooType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('foo');
        $resolver->setDefined('bar');
        $resolver->setDeprecated('bar', 'vendor/package', '1.1');
        $resolver->setDefault('empty_data', function (Options $options) {
            $foo = $options['foo'];

            return fn (FormInterface $form) => $form->getConfig()->getCompound() ? [$foo] : $foo;
        });
        $resolver->setAllowedTypes('foo', 'string');
        $resolver->setAllowedValues('foo', ['bar', 'baz']);
        $resolver->setNormalizer('foo', fn (Options $options, $value) => (string) $value);
        $resolver->setInfo('foo', 'Info');
    }
}
