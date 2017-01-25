<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AstGenerator\Tests\Hydrate\Type;

use PhpParser\Node\Expr;
use PhpParser\PrettyPrinter\Standard;
use Symfony\Component\AstGenerator\AstGeneratorInterface;
use Symfony\Component\AstGenerator\Hydrate\Type\CollectionTypeGenerator;
use Symfony\Component\PropertyInfo\Type;

class CollectionTypeGeneratorTest  extends \PHPUnit_Framework_TestCase
{
    /** @var Standard */
    protected $printer;

    public function setUp()
    {
        $this->printer = new Standard();
    }

    /**
     * @expectedException \Symfony\Component\AstGenerator\Exception\MissingContextException
     */
    public function testNoInput()
    {
        $itemGenerator = $this->getMockBuilder(AstGeneratorInterface::class)->getMock();
        $hydrateGenerator = new CollectionTypeGenerator($itemGenerator);
        $hydrateGenerator->generate(new Type('array', false, null, true));
    }

    /**
     * @expectedException \Symfony\Component\AstGenerator\Exception\MissingContextException
     */
    public function testNoOutput()
    {
        $itemGenerator = $this->getMockBuilder(AstGeneratorInterface::class)->getMock();
        $hydrateGenerator = new CollectionTypeGenerator($itemGenerator);
        $hydrateGenerator->generate(new Type('array', false, null, true), ['input' => new Expr\Variable('test')]);
    }

    public function testDefaultWithNumericalArray()
    {
        $collectionKeyType = new Type('int');
        $collectionValueType = new Type('string');
        $type = new Type('array', false, null, true, $collectionKeyType, $collectionValueType);

        $itemGenerator = $this->getItemGeneratorMock($collectionValueType);

        $generator = new CollectionTypeGenerator($itemGenerator);

        $this->assertTrue($generator->supportsGeneration($type));

        $input = [
            'foo',
            'bar',
        ];

        eval($this->printer->prettyPrint($generator->generate($type, [
            'input' => new Expr\Variable('input'),
            'output' => new Expr\Variable('output'),
        ])));

        $this->assertInternalType('array', $output);
        $this->assertCount(2, $output);
        $this->assertEquals('foo', $output[0]);
        $this->assertEquals('bar', $output[1]);
    }

    public function testDefaultWithMapArray()
    {
        $collectionKeyType = new Type('string');
        $collectionValueType = new Type('string');
        $type = new Type('array', false, null, true, $collectionKeyType, $collectionValueType);

        $itemGenerator = $this->getItemGeneratorMock($collectionValueType);

        $generator = new CollectionTypeGenerator($itemGenerator);

        $this->assertTrue($generator->supportsGeneration($type));

        $input = [
            'foo' => 'foo',
            'bar' => 'bar',
        ];

        eval($this->printer->prettyPrint($generator->generate($type, [
            'input' => new Expr\Variable('input'),
            'output' => new Expr\Variable('output'),
        ])));

        $this->assertInstanceOf('\stdClass', $output);
        $this->assertObjectHasAttribute('foo', $output);
        $this->assertObjectHasAttribute('bar', $output);
        $this->assertEquals('foo', $output->foo);
        $this->assertEquals('bar', $output->bar);
    }

    public function testCustomObject()
    {
        $collectionKeyType = new Type('string');
        $collectionValueType = new Type('string');
        $type = new Type('array', false, null, true, $collectionKeyType, $collectionValueType);

        $itemGenerator = $this->getItemGeneratorMock($collectionValueType);

        $generator = new CollectionTypeGenerator(
            $itemGenerator,
            CollectionTypeGenerator::COLLECTION_WITH_OBJECT,
            '\ArrayObject',
            CollectionTypeGenerator::OBJECT_ASSIGNMENT_ARRAY
        );

        $this->assertTrue($generator->supportsGeneration($type));

        $input = [
            'foo' => 'foo',
            'bar' => 'bar',
        ];

        eval($this->printer->prettyPrint($generator->generate($type, [
            'input' => new Expr\Variable('input'),
            'output' => new Expr\Variable('output'),
        ])));

        $this->assertInstanceOf('\ArrayObject', $output);
        $this->assertArrayHasKey('foo', $output);
        $this->assertArrayHasKey('bar', $output);
        $this->assertEquals('foo', $output['foo']);
        $this->assertEquals('bar', $output['bar']);
    }

    private function getItemGeneratorMock(Type $collectionValueType)
    {
        $itemGenerator = $this->getMockBuilder(AstGeneratorInterface::class)->getMock();
        $itemGenerator
            ->expects($this->any())
            ->method('supportsGeneration')
            ->with($collectionValueType)
            ->willReturn(true);
        $itemGenerator
            ->expects($this->any())
            ->method('generate')
            ->with($collectionValueType, $this->isType('array'))
            ->will($this->returnCallback(function ($object, array $context) {
                return [new Expr\Assign($context['output'], $context['input'])];
            }));

        return $itemGenerator;
    }
}
