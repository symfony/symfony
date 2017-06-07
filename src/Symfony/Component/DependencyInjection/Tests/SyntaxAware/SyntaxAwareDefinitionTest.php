<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\SyntaxAware\Tests;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\SyntaxAware\SyntaxAwareDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\ExpressionLanguage\Expression;

class SyntaxAwareDefinitionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideServiceSyntaxes
     */
    public function testResolveServices($expression, $expectedValue)
    {
        $definition = new SyntaxAwareDefinition();
        $definition->addArgument($expression);
        $actualArgument = $definition->getArgument(0);

        $this->assertEquals($expectedValue, $actualArgument);
    }

    public function provideServiceSyntaxes()
    {
        $tests = array();
        $tests[] = array('foo', 'foo');

        $tests[] = array('@foo', new Reference('foo', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE));
        $tests[] = array('@?foo', new Reference('foo', ContainerInterface::IGNORE_ON_INVALID_REFERENCE));

        $tests[] = array('@foo=', new Reference('foo', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, false));
        $tests[] = array('@?foo=', new Reference('foo', ContainerInterface::IGNORE_ON_INVALID_REFERENCE, false));

        // the escaped string version
        $tests[] = array('@@foo', '@foo');

        $tests[] = array(array('foo', '@bar'), array('foo', new Reference('bar')));

        $tests[] = array('@=service("bar")', new Expression('service("bar")'));

        return $tests;
    }

    public function testConstruct()
    {
        $definition = new SyntaxAwareDefinition('Acme\Foo', array('bar', '@pizza_chef'));
        $arguments = $definition->getArguments();

        $this->assertEquals(array('bar', new Reference('pizza_chef')), $arguments);
    }

    public function testSetArguments()
    {
        $definition = new SyntaxAwareDefinition();
        $definition->setArguments(array('bar', '@pizza_chef'));
        $arguments = $definition->getArguments();

        $this->assertEquals(array('bar', new Reference('pizza_chef')), $arguments);
    }

    public function testAddArgument()
    {
        $definition = new SyntaxAwareDefinition();
        $definition->addArgument('bar');
        $definition->addArgument('@pizza_chef');
        $arguments = $definition->getArguments();

        $this->assertEquals(array('bar', new Reference('pizza_chef')), $arguments);
    }

    public function testReplaceArgument()
    {
        $definition = new SyntaxAwareDefinition();
        $definition->addArgument('bar');
        $definition->replaceArgument(0, '@pizza_chef');
        $arguments = $definition->getArguments();

        $this->assertEquals(array(new Reference('pizza_chef')), $arguments);
    }

    public function testSetProperties()
    {
        $definition = new SyntaxAwareDefinition();
        $definition->setProperties(array('ingredients' => 'anchovies', 'chef' => '@pizza_chef'));
        $properties = $definition->getProperties();

        $this->assertEquals(array('ingredients' => 'anchovies', 'chef' => new Reference('pizza_chef')), $properties);
    }

    public function testSetProperty()
    {
        $definition = new SyntaxAwareDefinition();
        $definition->setProperty('ingredients', 'anchovies');
        $definition->setProperty('chef', '@pizza_chef');
        $properties = $definition->getProperties();

        $this->assertEquals(array('ingredients' => 'anchovies', 'chef' => new Reference('pizza_chef')), $properties);
    }

    public function testSetFactory()
    {
        $definition = new SyntaxAwareDefinition();
        $definition->setFactory('CulinarySchool::createChef');
        $this->assertEquals(array('CulinarySchool', 'createChef'), $definition->getFactory(), 'Class::method are not affected');

        $definition->setFactory('culinary_school:createChef');
        $this->assertEquals(array(new Reference('culinary_school'), 'createChef'), $definition->getFactory(), 'service:method is transformed into a Reference');

        $definition->setFactory(array('CulinarySchool', 'createChef'));
        $this->assertEquals(array('CulinarySchool', 'createChef'), $definition->getFactory(), 'An array without the @ is not changed');

        $definition->setFactory(array('@culinary_school', 'createChef'));
        $this->assertEquals(array(new Reference('culinary_school'), 'createChef'), $definition->getFactory(), 'An array with @ before the "object" is turned into a Reference');
    }

    public function testConfigurator()
    {
        $definition = new SyntaxAwareDefinition();

        $definition->setConfigurator('gordon_ramsay');
        $this->assertEquals('gordon_ramsay', $definition->getConfigurator(), 'Non-arrays are not transformed');

        $definition->setConfigurator(array('GordonRamsay', 'configureChefs'));
        $this->assertEquals(array('GordonRamsay', 'configureChefs'), $definition->getConfigurator(), 'An array without @ is not transformed');

        $definition->setConfigurator(array('@gordon_ramsay', 'configureChefs'));
        $this->assertEquals(array(new Reference('gordon_ramsay'), 'configureChefs'), $definition->getConfigurator(), 'An array with @ is transformed to a Reference');
    }
}
