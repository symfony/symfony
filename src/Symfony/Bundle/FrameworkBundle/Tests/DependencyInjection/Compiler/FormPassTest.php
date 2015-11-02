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
    /** @var ContainerBuilder */
    private $container;

    /** @var FormPass */
    private $pass;

    public function setUp()
    {
        $this->container = new ContainerBuilder();
        $this->pass = new FormPass();
        $this->container->addCompilerPass($this->pass);
    }

    public function testDoNothingIfFormExtensionNotLoaded()
    {
        $this->container->compile();

        $this->assertFalse($this->container->hasDefinition('form.extension'));
    }

    public function testAddTaggedTypes()
    {
        $this->registerDependencyInjectionExtension();

        $definition1 = new Definition(__CLASS__.'_Type1');
        $definition1->addTag('form.type');
        $definition2 = new Definition(__CLASS__.'_Type2');
        $definition2->addTag('form.type');

        $this->container->setDefinition('my.type1', $definition1);
        $this->container->setDefinition('my.type2', $definition2);

        $this->container->compile();

        $extDefinition = $this->container->getDefinition('form.extension');

        $this->assertEquals(array(
            // As of Symfony 2.8, the class is used to look up types
            __CLASS__.'_Type1' => 'my.type1',
            __CLASS__.'_Type2' => 'my.type2',
            // Before Symfony 2.8, the service ID was used as default alias
            'my.type1' => 'my.type1',
            'my.type2' => 'my.type2',
        ), $extDefinition->getArgument(1));
    }

    /**
     * @group legacy
     */
    public function testUseCustomAliasIfSet()
    {
        $this->registerDependencyInjectionExtension();

        $definition1 = new Definition(__CLASS__.'_Type1');
        $definition1->addTag('form.type', array('alias' => 'mytype1'));
        $definition2 = new Definition(__CLASS__.'_Type2');
        $definition2->addTag('form.type', array('alias' => 'mytype2'));

        $this->container->setDefinition('my.type1', $definition1);
        $this->container->setDefinition('my.type2', $definition2);

        $this->container->compile();

        $extDefinition = $this->container->getDefinition('form.extension');

        $this->assertEquals(array(
            __CLASS__.'_Type1' => 'my.type1',
            __CLASS__.'_Type2' => 'my.type2',
            'mytype1' => 'my.type1',
            'mytype2' => 'my.type2',
        ), $extDefinition->getArgument(1));
    }

    public function testAddTaggedTypeExtensions()
    {
        $this->registerDependencyInjectionExtension();

        $this->container->register('my.type_extension1', 'stdClass')
            ->addTag('form.type_extension', array('extended_type' => 'type1'));
        $this->container->register('my.type_extension2', 'stdClass')
            ->addTag('form.type_extension', array('extended_type' => 'type1'));
        $this->container->register('my.type_extension3', 'stdClass')
            ->addTag('form.type_extension', array('extended_type' => 'type2'));

        $this->container->compile();

        $extDefinition = $this->container->getDefinition('form.extension');

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
        $this->registerDependencyInjectionExtension();

        $this->container->register('my.type_extension1', 'stdClass')
            ->addTag('form.type_extension', array('alias' => 'type1'));
        $this->container->register('my.type_extension2', 'stdClass')
            ->addTag('form.type_extension', array('alias' => 'type2'));

        $this->container->compile();

        $extDefinition = $this->container->getDefinition('form.extension');

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
        $this->registerDependencyInjectionExtension();

        $definition1 = new Definition('stdClass');
        $definition1->addTag('form.type_guesser');
        $definition2 = new Definition('stdClass');
        $definition2->addTag('form.type_guesser');

        $this->container->setDefinition('my.guesser1', $definition1);
        $this->container->setDefinition('my.guesser2', $definition2);

        $this->container->compile();

        $extDefinition = $this->container->getDefinition('form.extension');

        $this->assertSame(array(
            'my.guesser1',
            'my.guesser2',
        ), $extDefinition->getArgument(3));
    }

    private function registerDependencyInjectionExtension()
    {
        $this->container->setDefinition('form.extension', new Definition('Symfony\Component\Form\Extension\DependencyInjection\DependencyInjectionExtension', array(
            new Reference('service_container'),
            array(),
            array(),
            array(),
        )));
    }
}

class FormPassTest_Type1 extends AbstractType
{
}

class FormPassTest_Type2 extends AbstractType
{
}
