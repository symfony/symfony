<?php

namespace Symfony\Component\AutoMapper\Tests\Transformer;

use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\Node\Param;
use PhpParser\PrettyPrinter\Standard;
use Symfony\Component\AutoMapper\Extractor\PropertyMapping;
use Symfony\Component\AutoMapper\Extractor\ReadAccessor;
use Symfony\Component\AutoMapper\Generator\UniqueVariableScope;
use Symfony\Component\AutoMapper\Transformer\TransformerInterface;

trait EvalTransformerTrait
{
    private function createTransformerFunction(TransformerInterface $transformer, PropertyMapping $propertyMapping = null): \Closure
    {
        if (null === $propertyMapping) {
            $propertyMapping = new PropertyMapping(
                new ReadAccessor(ReadAccessor::TYPE_PROPERTY, 'dummy'),
                null,
                null,
                $transformer,
                'dummy'
            );
        }

        $variableScope = new UniqueVariableScope();
        $inputName = $variableScope->getUniqueName('input');
        $inputExpr = new Expr\Variable($inputName);

        [$outputExpr, $stmts] = $transformer->transform($inputExpr, $propertyMapping, $variableScope);

        $stmts[] = new Stmt\Return_($outputExpr);

        $functionExpr = new Expr\Closure([
            'stmts' => $stmts,
            'params' => [new Param($inputExpr), new Param(new Expr\Variable('context'), new Expr\Array_())]
        ]);

        $printer = new Standard();
        $code = $printer->prettyPrint([new Stmt\Return_($functionExpr)]);

        return eval($code);
    }

    private function evalTransformer(TransformerInterface $transformer, $input, PropertyMapping $propertyMapping = null)
    {
        $function = $this->createTransformerFunction($transformer, $propertyMapping);

        return $function($input);
    }
}
