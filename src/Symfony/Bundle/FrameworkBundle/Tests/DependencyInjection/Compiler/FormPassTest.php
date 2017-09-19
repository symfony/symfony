<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\FormPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\AbstractType;

/**
 * @group legacy
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormPassTest extends TestCase
{
    public function testDoNothingIfFormExtensionNotLoaded()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new FormPass());

        $container->compile();

        $this->assertFalse($container->hasDefinition('form.extension'));
    }

    public function testAddTaggedTypes()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new FormPass());

        $extDefinition = new Definition('Symfony\Component\Form\Extension\DependencyInjection\DependencyInjectionExtension');
        $extDefinition->setPublic(true);
        $extDefinition->setArguments(array(
            new Reference('service_container'),
            array(),
            array(),
            array(),
        ));

        $container->setDefinition('form.extension', $extDefinition);
        $container->register('my.type1', __CLASS__.'_Type1')->addTag('form.type')->setPublic(true);
        $container->register('my.type2', __CLASS__.'_Type2')->addTag('form.type')->setPublic(true);

        $container->compile();

        $extDefinition = $container->getDefinition('form.extension');

        $this->assertEquals(array(
            __CLASS__.'_Type1' => 'my.type1',
            __CLASS__.'_Type2' => 'my.type2',
        ), $extDefinition->getArgument(1));
    }

    /**
     * @dataProvider addTaggedTypeExtensionsDataProvider
     */
    public function testAddTaggedTypeExtensions(array $extensions, array $expectedRegisteredExtensions)
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new FormPass());

        $extDefinition = new Definition('Symfony\Component\Form\Extension\DependencyInjection\DependencyInjectionExtension', array(
            new Reference('service_container'),
            array(),
            array(),
            array(),
        ));
        $extDefinition->setPublic(true);

        $container->setDefinition('form.extension', $extDefinition);

        foreach ($extensions as $serviceId => $tag) {
            $container->register($serviceId, 'stdClass')->addTag('form.type_extension', $tag);
        }

        $container->compile();

        $extDefinition = $container->getDefinition('form.extension');
        $this->assertSame($expectedRegisteredExtensions, $extDefinition->getArgument(2));
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
                    'type1' => array('my.type_extension1', 'my.type_extension2'),
                    'type2' => array('my.type_extension3'),
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
                    'type1' => array('my.type_extension2', 'my.type_extension1', 'my.type_extension3'),
                    'type2' => array('my.type_extension4', 'my.type_extension5', 'my.type_extension6'),
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
        $container = new ContainerBuilder();
        $container->addCompilerPass(new FormPass());

        $extDefinition = new Definition('Symfony\Component\Form\Extension\DependencyInjection\DependencyInjectionExtension', array(
            new Reference('service_container'),
            array(),
            array(),
            array(),
        ));
        $extDefinition->setPublic(true);

        $container->setDefinition('form.extension', $extDefinition);
        $container->register('my.type_extension', 'stdClass')
            ->addTag('form.type_extension');

        $container->compile();
    }

    public function testAddTaggedGuessers()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new FormPass());

        $extDefinition = new Definition('Symfony\Component\Form\Extension\DependencyInjection\DependencyInjectionExtension');
        $extDefinition->setPublic(true);
        $extDefinition->setArguments(array(
            new Reference('service_container'),
            array(),
            array(),
            array(),
        ));

        $definition1 = new Definition('stdClass');
        $definition1->addTag('form.type_guesser');
        $definition2 = new Definition('stdClass');
        $definition2->addTag('form.type_guesser');

        $container->setDefinition('form.extension', $extDefinition);
        $container->setDefinition('my.guesser1', $definition1);
        $container->setDefinition('my.guesser2', $definition2);

        $container->compile();

        $extDefinition = $container->getDefinition('form.extension');

        $this->assertSame(array(
            'my.guesser1',
            'my.guesser2',
        ), $extDefinition->getArgument(3));
    }

    /**
     * @dataProvider privateTaggedServicesProvider
     */
    public function testPrivateTaggedServices($id, $tagName)
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new FormPass());

        $extDefinition = new Definition('Symfony\Component\Form\Extension\DependencyInjection\DependencyInjectionExtension');
        $extDefinition->setArguments(array(
            new Reference('service_container'),
            array(),
            array(),
            array(),
        ));

        $container->setDefinition('form.extension', $extDefinition);
        $container->register($id, 'stdClass')->setPublic(false)->addTag($tagName, array('extended_type' => 'Foo'));

        $container->compile();
        $this->assertTrue($container->getDefinition($id)->isPublic());
    }

    public function privateTaggedServicesProvider()
    {
        return array(
            array('my.type', 'form.type'),
            array('my.type_extension', 'form.type_extension'),
            array('my.guesser', 'form.type_guesser'),
        );
    }
}

class FormPassTest_Type1 extends AbstractType
{
}

class FormPassTest_Type2 extends AbstractType
{
}
