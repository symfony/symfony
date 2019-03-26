<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper\Transformer;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use Symfony\Component\AutoMapper\Extractor\PropertyMapping;
use Symfony\Component\AutoMapper\Generator\UniqueVariableScope;
use Symfony\Component\AutoMapper\MapperContext;
use Symfony\Component\PropertyInfo\Type;

/**
 * Transform to an object which can be mapped by AutoMapper (sub mapping).
 *
 * @expiremental in 4.3
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
final class ObjectTransformer implements TransformerInterface
{
    private $sourceType;

    private $targetType;

    public function __construct(Type $sourceType, Type $targetType)
    {
        $this->sourceType = $sourceType;
        $this->targetType = $targetType;
    }

    /**
     * {@inheritdoc}
     */
    public function transform(Expr $input, PropertyMapping $propertyMapping, UniqueVariableScope $uniqueVariableScope): array
    {
        $mapperName = $this->getDependencyName();

        return [new Expr\MethodCall(new Expr\ArrayDimFetch(
                new Expr\PropertyFetch(new Expr\Variable('this'), 'mappers'),
            new Scalar\String_($mapperName)
            ), 'map', [
                new Arg($input),
                new Arg(new Expr\StaticCall(new Name\FullyQualified(MapperContext::class), 'withNewContext', [
                    new Arg(new Expr\Variable('context')),
                    new Arg(new Scalar\String_($propertyMapping->getProperty())),
                ])),
        ]), []];
    }

    /**
     * {@inheritdoc}
     */
    public function assignByRef(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getDependencies(): array
    {
        return [new MapperDependency($this->getDependencyName(), $this->getSource(), $this->getTarget())];
    }

    private function getDependencyName(): string
    {
        return 'Mapper_'.$this->getSource().'_'.$this->getTarget();
    }

    private function getSource(): string
    {
        $sourceTypeName = 'array';

        if (Type::BUILTIN_TYPE_OBJECT === $this->sourceType->getBuiltinType()) {
            $sourceTypeName = $this->sourceType->getClassName();
        }

        return $sourceTypeName;
    }

    private function getTarget(): string
    {
        $targetTypeName = 'array';

        if (Type::BUILTIN_TYPE_OBJECT === $this->targetType->getBuiltinType()) {
            $targetTypeName = $this->targetType->getClassName();
        }

        return $targetTypeName;
    }
}
