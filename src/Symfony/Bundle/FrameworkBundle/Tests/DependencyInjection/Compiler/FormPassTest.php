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

use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\FormPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\AbstractType;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormPassTest extends \PHPUnit_Framework_TestCase
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
        $extDefinition->setArguments(array(
            new Reference('service_container'),
            array(),
            array(),
            array(),
        ));

        $definition1 = new Definition(__CLASS__.'_Type1');
        $definition1->addTag('form.type');
        $definition2 = new Definition(__CLASS__.'_Type2');
        $definition2->addTag('form.type');

        $container->setDefinition('form.extension', $extDefinition);
        $container->setDefinition('my.type1', $definition1);
        $container->setDefinition('my.type2', $definition2);

        $container->compile();

        $extDefinition = $container->getDefinition('form.extension');

        $this->assertEquals(array(
            __CLASS__.'_Type1' => 'my.type1',
            __CLASS__.'_Type2' => 'my.type2',
        ), $extDefinition->getArgument(1));
    }

    public function testAddTaggedTypeExtensions()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new FormPass());

        $extDefinition = new Definition('Symfony\Component\Form\Extension\DependencyInjection\DependencyInjectionExtension', array(
            new Reference('service_container'),
            array(),
            array(),
            array(),
        ));

        $container->setDefinition('form.extension', $extDefinition);
        $container->register('my.type_extension1', 'stdClass')
            ->addTag('form.type_extension', array('extended_type' => 'type1'));
        $container->register('my.type_extension2', 'stdClass')
            ->addTag('form.type_extension', array('extended_type' => 'type1'));
        $container->register('my.type_extension3', 'stdClass')
            ->addTag('form.type_extension', array('extended_type' => 'type2'));

        $container->compile();

        $extDefinition = $container->getDefinition('form.extension');

        $this->assertSame(array(
            'type1' => array(
                'my.type_extension1',
                'my.type_extension2',
            ),
            'type2' => array(
                'my.type_extension3',
            ),
        ), $extDefinition->getArgument(2));
    }

    /**
     * @group legacy
     */
    public function testAliasOptionForTaggedTypeExtensions()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new FormPass());

        $extDefinition = new Definition('Symfony\Component\Form\Extension\DependencyInjection\DependencyInjectionExtension', array(
            new Reference('service_container'),
            array(),
            array(),
            array(),
        ));

        $container->setDefinition('form.extension', $extDefinition);
        $container->register('my.type_extension1', 'stdClass')
            ->addTag('form.type_extension', array('alias' => 'type1'));
        $container->register('my.type_extension2', 'stdClass')
            ->addTag('form.type_extension', array('alias' => 'type2'));

        $container->compile();

        $extDefinition = $container->getDefinition('form.extension');

        $this->assertSame(array(
            'type1' => array(
                'my.type_extension1',
            ),
            'type2' => array(
                'my.type_extension2',
            ),
        ), $extDefinition->getArgument(2));
    }

    public function testAddTaggedGuessers()
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
}

class FormPassTest_Type1 extends AbstractType
{
}

class FormPassTest_Type2 extends AbstractType
{
}
