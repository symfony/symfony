<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AstGenerator\Tests\Hydrate;

use PhpParser\Node\Expr;
use PhpParser\PrettyPrinter\Standard;
use Prophecy\Argument;
use Symfony\Component\AstGenerator\AstGeneratorInterface;
use Symfony\Component\AstGenerator\Hydrate\StdClassHydrateGenerator;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\Type;

class StdClassHydrateGeneratorTest extends \PHPUnit_Framework_TestCase
{
    /** @var Standard */
    protected $printer;

    public function setUp()
    {
        $this->printer = new Standard();
    }

    public function testHydrateGenerator()
    {
        $propertyInfoExtractor = $this->prophesize(PropertyInfoExtractorInterface::class);
        $propertyInfoExtractor->getProperties(Foo::class, Argument::type('array'))->willReturn(['foo', 'bar']);
        $propertyInfoExtractor->isReadable(Foo::class, 'foo', Argument::type('array'))->willReturn(true);
        $propertyInfoExtractor->isReadable(Foo::class, 'bar', Argument::type('array'))->willReturn(false);
        $propertyInfoExtractor->getTypes(Foo::class, 'foo', Argument::type('array'))->willReturn([
            new Type('string')
        ]);
        $hydrateGenerator = new StdClassHydrateGenerator($propertyInfoExtractor->reveal(), new FooTypeGenerator());

        $this->assertTrue($hydrateGenerator->supportsGeneration(Foo::class));

        $fooObject = new Foo();
        $fooObject->foo = "test";

        eval($this->printer->prettyPrint($hydrateGenerator->generate(Foo::class, [
            'input' => new Expr\Variable('fooObject'),
            'output' => new Expr\Variable('dummyStdClass')
        ])));

        $this->assertInstanceOf('stdClass', $dummyStdClass);
        $this->assertObjectHasAttribute('foo', $dummyStdClass);
        $this->assertEquals('test', $dummyStdClass->foo);
    }

    /**
     * @expectedException \Symfony\Component\AstGenerator\Exception\MissingContextException
     */
    public function testNoInput()
    {
        $propertyInfoExtractor = $this->prophesize(PropertyInfoExtractorInterface::class);
        $hydrateGenerator = new StdClassHydrateGenerator($propertyInfoExtractor->reveal(), new FooTypeGenerator());
        $hydrateGenerator->generate(Foo::class);
    }

    /**
     * @expectedException \Symfony\Component\AstGenerator\Exception\MissingContextException
     */
    public function testNoOutput()
    {
        $propertyInfoExtractor = $this->prophesize(PropertyInfoExtractorInterface::class);
        $hydrateGenerator = new StdClassHydrateGenerator($propertyInfoExtractor->reveal(), new FooTypeGenerator());
        $hydrateGenerator->generate(Foo::class, ['input' => new Expr\Variable("test")]);
    }
}

class Foo
{
    public $foo;

    public $bar;

    /**
     * @return mixed
     */
    public function getFoo()
    {
        return $this->foo;
    }

    /**
     * @param mixed $bar
     */
    public function setBar($bar)
    {
        $this->bar = $bar;
    }
}

class FooTypeGenerator implements AstGeneratorInterface
{
    public function generate($object, array $context = [])
    {
        if (!isset($context['input'])) {
            throw new \Exception('no input');
        }

        if (!isset($context['output'])) {
            throw new \Exception('no output');
        }

        return [new Expr\Assign($context['output'], $context['input'])];
    }

    public function supportsGeneration($object)
    {
        return true;
    }
}
