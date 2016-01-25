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
use Symfony\Component\AstGenerator\Hydrate\Type\TypeGenerator;
use Symfony\Component\PropertyInfo\Type;

class TypeGeneratorTest extends \PHPUnit_Framework_TestCase
{
    /** @var Standard */
    protected $printer;

    /** @var TypeGenerator */
    private $typeGenerator;

    public function setUp()
    {
        $this->typeGenerator = new TypeGenerator();
        $this->printer = new Standard();
    }

    /**
     * @expectedException \Symfony\Component\AstGenerator\Exception\MissingContextException
     */
    public function testNoInput()
    {
        $this->typeGenerator->generate(new Type('string'));
    }

    /**
     * @expectedException \Symfony\Component\AstGenerator\Exception\MissingContextException
     */
    public function testNoOutput()
    {
        $this->typeGenerator->generate(new Type('string'), ['input' => new Expr\Variable('inputData')]);
    }

    public function testString()
    {
        $type = new Type('string');
        $input = new Expr\Variable('inputData');
        $output = new Expr\Variable('outputData');

        $inputData = "test";
        $outputData = null;

        $this->assertTrue($this->typeGenerator->supportsGeneration($type));

        eval($this->printer->prettyPrint($this->typeGenerator->generate($type, ['input' => $input, 'output' => $output])));

        $this->assertSame($inputData, $outputData);
    }

    public function testInteger()
    {
        $type = new Type('int');
        $input = new Expr\Variable('inputData');
        $output = new Expr\Variable('outputData');

        $inputData = 40;
        $outputData = null;

        $this->assertTrue($this->typeGenerator->supportsGeneration($type));

        eval($this->printer->prettyPrint($this->typeGenerator->generate($type, ['input' => $input, 'output' => $output])));

        $this->assertSame($inputData, $outputData);
    }

    public function testNull()
    {
        $type = new Type('null');
        $input = new Expr\Variable('inputData');
        $output = new Expr\Variable('outputData');

        $inputData = null;
        $outputData = "test";

        $this->assertTrue($this->typeGenerator->supportsGeneration($type));

        eval($this->printer->prettyPrint($this->typeGenerator->generate($type, ['input' => $input, 'output' => $output])));

        $this->assertSame($inputData, $outputData);
    }

    public function testFloat()
    {
        $type = new Type('float');
        $input = new Expr\Variable('inputData');
        $output = new Expr\Variable('outputData');

        $inputData = 2.5;
        $outputData = null;

        $this->assertTrue($this->typeGenerator->supportsGeneration($type));

        eval($this->printer->prettyPrint($this->typeGenerator->generate($type, ['input' => $input, 'output' => $output])));

        $this->assertSame($inputData, $outputData);
    }

    public function testBool()
    {
        $type = new Type('bool');
        $input = new Expr\Variable('inputData');
        $output = new Expr\Variable('outputData');

        $inputData = true;
        $outputData = null;

        $this->assertTrue($this->typeGenerator->supportsGeneration($type));

        eval($this->printer->prettyPrint($this->typeGenerator->generate($type, ['input' => $input, 'output' => $output])));

        $this->assertSame($inputData, $outputData);
    }

    public function testStringConditionOk()
    {
        $type = new Type('string');
        $input = new Expr\Variable('inputData');
        $output = new Expr\Variable('outputData');

        $inputData = "test";
        $outputData = null;

        eval($this->printer->prettyPrint($this->typeGenerator->generate($type, ['condition' => true, 'input' => $input, 'output' => $output])));

        $this->assertSame($inputData, $outputData);
    }

    public function testIntegerConditionOk()
    {
        $type = new Type('int');
        $input = new Expr\Variable('inputData');
        $output = new Expr\Variable('outputData');

        $inputData = 40;
        $outputData = null;

        eval($this->printer->prettyPrint($this->typeGenerator->generate($type, ['condition' => true,'input' => $input, 'output' => $output])));

        $this->assertSame($inputData, $outputData);
    }

    public function testNullConditionOk()
    {
        $type = new Type('null');
        $input = new Expr\Variable('inputData');
        $output = new Expr\Variable('outputData');

        $inputData = null;
        $outputData = "test";

        eval($this->printer->prettyPrint($this->typeGenerator->generate($type, ['condition' => true,'input' => $input, 'output' => $output])));

        $this->assertSame($inputData, $outputData);
    }

    public function testFloatConditionOk()
    {
        $type = new Type('float');
        $input = new Expr\Variable('inputData');
        $output = new Expr\Variable('outputData');

        $inputData = 2.5;
        $outputData = null;

        eval($this->printer->prettyPrint($this->typeGenerator->generate($type, ['condition' => true,'input' => $input, 'output' => $output])));

        $this->assertSame($inputData, $outputData);
    }

    public function testBoolConditionOk()
    {
        $type = new Type('bool');
        $input = new Expr\Variable('inputData');
        $output = new Expr\Variable('outputData');

        $inputData = true;
        $outputData = null;

        eval($this->printer->prettyPrint($this->typeGenerator->generate($type, ['condition' => true, 'input' => $input, 'output' => $output])));

        $this->assertSame($inputData, $outputData);
    }

    public function testStringConditionNOK()
    {
        $type = new Type('string');
        $input = new Expr\Variable('inputData');
        $output = new Expr\Variable('outputData');

        $inputData = 1;
        $outputData = null;

        eval($this->printer->prettyPrint($this->typeGenerator->generate($type, ['condition' => true, 'input' => $input, 'output' => $output])));

        $this->assertNull($outputData);
    }

    public function testIntegerConditionNOK()
    {
        $type = new Type('int');
        $input = new Expr\Variable('inputData');
        $output = new Expr\Variable('outputData');

        $inputData = "test";
        $outputData = null;

        eval($this->printer->prettyPrint($this->typeGenerator->generate($type, ['condition' => true,'input' => $input, 'output' => $output])));

        $this->assertNull($outputData);
    }

    public function testNullConditionNOK()
    {
        $type = new Type('null');
        $input = new Expr\Variable('inputData');
        $output = new Expr\Variable('outputData');

        $inputData = "test";
        $outputData = "test";

        eval($this->printer->prettyPrint($this->typeGenerator->generate($type, ['condition' => true,'input' => $input, 'output' => $output])));

        $this->assertNotNull($outputData);
    }

    public function testFloatConditionNOK()
    {
        $type = new Type('float');
        $input = new Expr\Variable('inputData');
        $output = new Expr\Variable('outputData');

        $inputData = "test";
        $outputData = null;

        eval($this->printer->prettyPrint($this->typeGenerator->generate($type, ['condition' => true,'input' => $input, 'output' => $output])));

        $this->assertNull($outputData);
    }

    public function testBoolConditionNOK()
    {
        $type = new Type('bool');
        $input = new Expr\Variable('inputData');
        $output = new Expr\Variable('outputData');

        $inputData = "test";
        $outputData = null;

        eval($this->printer->prettyPrint($this->typeGenerator->generate($type, ['condition' => true, 'input' => $input, 'output' => $output])));

        $this->assertNull($outputData);
    }
}
