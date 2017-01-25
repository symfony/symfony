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

class ObjectHydrateFromStdClassGeneratorTest extends AbstractHydratorTest
{
    public function testHydrateGenerator()
    {
        $propertyInfoExtractor = $this->getPropertyInfoExtractor(DummyObjectStdClass::class);
        $hydrateGenerator = new ObjectHydrateFromStdClassGenerator($propertyInfoExtractor, new DummyObjectStdClassTypeGenerator());

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
        $propertyInfoExtractor = $this->getMockBuilder(PropertyInfoExtractorInterface::class)->getMock();
        $hydrateGenerator = new ObjectHydrateFromStdClassGenerator($propertyInfoExtractor, new DummyObjectStdClassTypeGenerator());
        $hydrateGenerator->generate(DummyObjectStdClass::class);
    }

    /**
     * @expectedException \Symfony\Component\AstGenerator\Exception\MissingContextException
     */
    public function testNoOutput()
    {
        $propertyInfoExtractor = $this->getMockBuilder(PropertyInfoExtractorInterface::class)->getMock();
        $hydrateGenerator = new ObjectHydrateFromStdClassGenerator($propertyInfoExtractor, new DummyObjectStdClassTypeGenerator());
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
