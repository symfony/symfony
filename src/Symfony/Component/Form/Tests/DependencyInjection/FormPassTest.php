<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DependencyInjection\FormPass;
use Symfony\Component\Form\FormRegistryInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormPassTest extends TestCase
{
    public function testDoNothingIfFormExtensionNotLoaded()
    {
        $container = $this->createContainerBuilder();

        $container->compile();

        $this->assertFalse($container->hasDefinition('form.extension'));
    }

    public function testDoNothingIfDebugCommandNotLoaded()
    {
        $container = $this->createContainerBuilder();

        $container->compile();

        $this->assertFalse($container->hasDefinition('console.command.form_debug'));
    }

    public function testAddTaggedTypes()
    {
        $container = $this->createContainerBuilder();

        $container->setDefinition('form.extension', $this->createExtensionDefinition());
        $container->register('my.type1', __CLASS__.'_Type1')->addTag('form.type')->setPublic(true);
        $container->register('my.type2', __CLASS__.'_Type2')->addTag('form.type')->setPublic(true);

        $container->compile();

        $extDefinition = $container->getDefinition('form.extension');

        $this->assertEquals(
            (new Definition(ServiceLocator::class, array(array(
                __CLASS__.'_Type1' => new ServiceClosureArgument(new Reference('my.type1')),
                __CLASS__.'_Type2' => new ServiceClosureArgument(new Reference('my.type2')),
            ))))->addTag('container.service_locator')->setPublic(false),
            $extDefinition->getArgument(0)
        );
    }

    public function testAddTaggedTypesToDebugCommand()
    {
        $container = $this->createContainerBuilder();

        $container->setDefinition('form.extension', $this->createExtensionDefinition());
        $container->setDefinition('console.command.form_debug', $this->createDebugCommandDefinition());
        $container->register('my.type1', __CLASS__.'_Type1')->addTag('form.type')->setPublic(true);
        $container->register('my.type2', __CLASS__.'_Type2')->addTag('form.type')->setPublic(true);

        $container->compile();

        $cmdDefinition = $container->getDefinition('console.command.form_debug');

        $this->assertEquals(
            array(
                'Symfony\Component\Form\Extension\Core\Type',
                __NAMESPACE__,
            ),
            $cmdDefinition->getArgument(1)
        );
    }

    /**
     * @dataProvider addTaggedTypeExtensionsDataProvider
     */
    public function testAddTaggedTypeExtensions(array $extensions, array $expectedRegisteredExtensions)
    {
        $container = $this->createContainerBuilder();

        $container->setDefinition('form.extension', $this->createExtensionDefinition());

        foreach ($extensions as $serviceId => $tag) {
            $container->register($serviceId, 'stdClass')->addTag('form.type_extension', $tag);
        }

        $container->compile();

        $extDefinition = $container->getDefinition('form.extension');
        $this->assertEquals($expectedRegisteredExtensions, $extDefinition->getArgument(1));
    }

    /**
     * @return array
     */
    public function addTaggedTypeExtensionsDataProvider()
    {
        return array(
            array(
                array(
                    'my.type_extension1' => array('extended_type' => 'type1'),
                    'my.type_extension2' => array('extended_type' => 'type1'),
                    'my.type_extension3' => array('extended_type' => 'type2'),
                ),
                array(
                    'type1' => new IteratorArgument(array(
                        new Reference('my.type_extension1'),
                        new Reference('my.type_extension2'),
                    )),
                    'type2' => new IteratorArgument(array(new Reference('my.type_extension3'))),
                ),
            ),
            array(
                array(
                    'my.type_extension1' => array('extended_type' => 'type1', 'priority' => 1),
                    'my.type_extension2' => array('extended_type' => 'type1', 'priority' => 2),
                    'my.type_extension3' => array('extended_type' => 'type1', 'priority' => -1),
                    'my.type_extension4' => array('extended_type' => 'type2', 'priority' => 2),
                    'my.type_extension5' => array('extended_type' => 'type2', 'priority' => 1),
                    'my.type_extension6' => array('extended_type' => 'type2', 'priority' => 1),
                ),
                array(
                    'type1' => new IteratorArgument(array(
                        new Reference('my.type_extension2'),
                        new Reference('my.type_extension1'),
                        new Reference('my.type_extension3'),
                    )),
                    'type2' => new IteratorArgument(array(
                        new Reference('my.type_extension4'),
                        new Reference('my.type_extension5'),
                        new Reference('my.type_extension6'),
                    )),
                ),
            ),
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage extended-type attribute, none was configured for the "my.type_extension" service
     */
    public function testAddTaggedFormTypeExtensionWithoutExtendedTypeAttribute()
    {
        $container = $this->createContainerBuilder();

        $container->setDefinition('form.extension', $this->createExtensionDefinition());
        $container->register('my.type_extension', 'stdClass')
            ->setPublic(true)
            ->addTag('form.type_extension');

        $container->compile();
    }

    public function testAddTaggedGuessers()
    {
        $container = $this->createContainerBuilder();

        $definition1 = new Definition('stdClass');
        $definition1->addTag('form.type_guesser');
        $definition2 = new Definition('stdClass');
        $definition2->addTag('form.type_guesser');

        $container->setDefinition('form.extension', $this->createExtensionDefinition());
        $container->setDefinition('my.guesser1', $definition1)->setPublic(true);
        $container->setDefinition('my.guesser2', $definition2)->setPublic(true);

        $container->compile();

        $extDefinition = $container->getDefinition('form.extension');

        $this->assertEquals(
            new IteratorArgument(array(
                new Reference('my.guesser1'),
                new Reference('my.guesser2'),
            )),
            $extDefinition->getArgument(2)
        );
    }

    /**
     * @dataProvider privateTaggedServicesProvider
     */
    public function testPrivateTaggedServices($id, $tagName, callable $assertion, array $tagAttributes = array())
    {
        $formPass = new FormPass();
        $container = new ContainerBuilder();

        $container->setDefinition('form.extension', $this->createExtensionDefinition());
        $container->register($id, 'stdClass')->setPublic(false)->addTag($tagName, $tagAttributes);
        $formPass->process($container);

        $assertion($container);
    }

    public function privateTaggedServicesProvider()
    {
        return array(
            array(
                'my.type',
                'form.type',
                function (ContainerBuilder $container) {
                    $formTypes = $container->getDefinition('form.extension')->getArgument(0);

                    $this->assertInstanceOf(Reference::class, $formTypes);

                    $locator = $container->getDefinition((string) $formTypes);
                    $expectedLocatorMap = array(
                        'stdClass' => new ServiceClosureArgument(new Reference('my.type')),
                    );

                    $this->assertInstanceOf(Definition::class, $locator);
                    $this->assertEquals($expectedLocatorMap, $locator->getArgument(0));
                },
            ),
            array(
                'my.type_extension',
                'form.type_extension',
                function (ContainerBuilder $container) {
                    $this->assertEquals(
                        array('Symfony\Component\Form\Extension\Core\Type\FormType' => new IteratorArgument(array(new Reference('my.type_extension')))),
                        $container->getDefinition('form.extension')->getArgument(1)
                    );
                },
                array('extended_type' => 'Symfony\Component\Form\Extension\Core\Type\FormType'),
            ),
            array('my.guesser', 'form.type_guesser', function (ContainerBuilder $container) {
                $this->assertEquals(new IteratorArgument(array(new Reference('my.guesser'))), $container->getDefinition('form.extension')->getArgument(2));
            }),
        );
    }

    private function createExtensionDefinition()
    {
        $definition = new Definition('Symfony\Component\Form\Extension\DependencyInjection\DependencyInjectionExtension');
        $definition->setPublic(true);
        $definition->setArguments(array(
            array(),
            array(),
            new IteratorArgument(array()),
        ));

        return $definition;
    }

    private function createDebugCommandDefinition()
    {
        $definition = new Definition('Symfony\Component\Form\Command\DebugCommand');
        $definition->setPublic(true);
        $definition->setArguments(array(
            $formRegistry = $this->getMockBuilder(FormRegistryInterface::class)->getMock(),
            array(),
            array('Symfony\Component\Form\Extension\Core\Type'),
        ));

        return $definition;
    }

    private function createContainerBuilder()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new FormPass());

        return $container;
    }
}

class FormPassTest_Type1 extends AbstractType
{
}

class FormPassTest_Type2 extends AbstractType
{
}
