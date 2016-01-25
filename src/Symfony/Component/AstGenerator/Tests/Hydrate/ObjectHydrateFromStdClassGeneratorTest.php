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
use Symfony\Component\AstGenerator\Hydrate\ObjectHydrateFromStdClassGenerator;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\Type;

class ObjectHydrateFromStdClassGeneratorTest extends \PHPUnit_Framework_TestCase
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
        $propertyInfoExtractor->getProperties(DummyObjectStdClass::class, Argument::type('array'))->willReturn(['foo', 'bar']);
        $propertyInfoExtractor->isWritable(DummyObjectStdClass::class, 'foo', Argument::type('array'))->willReturn(false);
        $propertyInfoExtractor->isWritable(DummyObjectStdClass::class, 'bar', Argument::type('array'))->willReturn(true);
        $propertyInfoExtractor->getTypes(DummyObjectStdClass::class, 'bar', Argument::type('array'))->willReturn([
            new Type('string'),
        ]);
        $hydrateGenerator = new ObjectHydrateFromStdClassGenerator($propertyInfoExtractor->reveal(), new DummyObjectStdClassTypeGenerator());

        $this->assertTrue($hydrateGenerator->supportsGeneration(DummyObjectStdClass::class));

        $stdClass = new \stdClass();
        $stdClass->bar = "test";

        eval($this->printer->prettyPrint($hydrateGenerator->generate(DummyObjectStdClass::class, [
            'input' => new Expr\Variable('stdClass'),
            'output' => new Expr\Variable('object'),
        ])));

        $this->assertInstanceOf(DummyObjectStdClass::class, $object);
        $this->assertEquals('test', $object->bar);
    }

    /**
     * @expectedException \Symfony\Component\AstGenerator\Exception\MissingContextException
     */
    public function testNoInput()
    {
        $propertyInfoExtractor = $this->prophesize(PropertyInfoExtractorInterface::class);
        $hydrateGenerator = new ObjectHydrateFromStdClassGenerator($propertyInfoExtractor->reveal(), new DummyObjectStdClassTypeGenerator());
        $hydrateGenerator->generate(DummyObjectStdClass::class);
    }

    /**
     * @expectedException \Symfony\Component\AstGenerator\Exception\MissingContextException
     */
    public function testNoOutput()
    {
        $propertyInfoExtractor = $this->prophesize(PropertyInfoExtractorInterface::class);
        $hydrateGenerator = new ObjectHydrateFromStdClassGenerator($propertyInfoExtractor->reveal(), new DummyObjectStdClassTypeGenerator());
        $hydrateGenerator->generate(DummyObjectStdClass::class, ['input' => new Expr\Variable('test')]);
    }
}

class DummyObjectStdClass
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

class DummyObjectStdClassTypeGenerator implements AstGeneratorInterface
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
