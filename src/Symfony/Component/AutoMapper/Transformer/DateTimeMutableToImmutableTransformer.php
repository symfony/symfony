<?php

namespace Symfony\Component\AutoMapper\Transformer;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use Symfony\Component\AutoMapper\Extractor\PropertyMapping;
use Symfony\Component\AutoMapper\Generator\UniqueVariableScope;

final class DateTimeMutableToImmutableTransformer implements TransformerInterface
{
    /**
     * {@inheritdoc}
     */
    public function transform(Expr $input, PropertyMapping $propertyMapping, UniqueVariableScope $uniqueVariableScope): array
    {
        return [
            new Expr\StaticCall(new Name\FullyQualified(\DateTimeImmutable::class), 'createFromMutable', [
                new Arg($input)
            ]),
            []
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getDependencies(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function assignByRef(): bool
    {
        return false;
    }
}
