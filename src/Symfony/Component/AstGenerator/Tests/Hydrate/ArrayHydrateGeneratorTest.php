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
use Symfony\Component\AstGenerator\Hydrate\ArrayHydrateGenerator;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\Type;

class ArrayHydrateGeneratorTest extends \PHPUnit_Framework_TestCase
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
        $propertyInfoExtractor->getProperties(Dummy::class, Argument::type('array'))->willReturn(['foo', 'bar']);
        $propertyInfoExtractor->isReadable(Dummy::class, 'foo', Argument::type('array'))->willReturn(true);
        $propertyInfoExtractor->isReadable(Dummy::class, 'bar', Argument::type('array'))->willReturn(false);
        $propertyInfoExtractor->getTypes(Dummy::class, 'foo', Argument::type('array'))->willReturn([
            new Type('string'),
        ]);
        $hydrateGenerator = new ArrayHydrateGenerator($propertyInfoExtractor->reveal(), new DummyTypeGenerator());

        $this->assertTrue($hydrateGenerator->supportsGeneration(Dummy::class));

        $dummyObject = new Dummy();
        $dummyObject->foo = 'test';

        eval($this->printer->prettyPrint($hydrateGenerator->generate(Dummy::class, [
            'input' => new Expr\Variable('dummyObject'),
            'output' => new Expr\Variable('dummyArray'),
        ])));

        $this->assertInternalType('array', $dummyArray);
        $this->assertArrayHasKey('foo', $dummyArray);
        $this->assertEquals('test', $dummyArray['foo']);
    }

    /**
     * @expectedException \Symfony\Component\AstGenerator\Exception\MissingContextException
     */
    public function testNoInput()
    {
        $propertyInfoExtractor = $this->prophesize(PropertyInfoExtractorInterface::class);
        $hydrateGenerator = new ArrayHydrateGenerator($propertyInfoExtractor->reveal(), new DummyTypeGenerator());
        $hydrateGenerator->generate(Dummy::class);
    }

    /**
     * @expectedException \Symfony\Component\AstGenerator\Exception\MissingContextException
     */
    public function testNoOutput()
    {
        $propertyInfoExtractor = $this->prophesize(PropertyInfoExtractorInterface::class);
        $hydrateGenerator = new ArrayHydrateGenerator($propertyInfoExtractor->reveal(), new DummyTypeGenerator());
        $hydrateGenerator->generate(Dummy::class, ['input' => new Expr\Variable('test')]);
    }
}

class Dummy
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

class DummyTypeGenerator implements AstGeneratorInterface
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
