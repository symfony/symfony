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
use PhpParser\Node\Scalar;
use Symfony\Component\AutoMapper\Extractor\PropertyMapping;
use Symfony\Component\AutoMapper\Generator\UniqueVariableScope;

/**
 * Handle custom callback transformation.
 *
 * @expiremental in 4.3
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
final class CallbackTransformer implements TransformerInterface
{
    private $callbackName;

    public function __construct(string $callbackName)
    {
        $this->callbackName = $callbackName;
    }

    /**
     * {@inheritdoc}
     */
    public function transform(Expr $input, PropertyMapping $propertyMapping, UniqueVariableScope $uniqueVariableScope): array
    {
        /*
         * $output = $this->callbacks[$callbackName]($input);
         */
        return [new Expr\FuncCall(
            new Expr\ArrayDimFetch(new Expr\PropertyFetch(new Expr\Variable('this'), 'callbacks'), new Scalar\String_($this->callbackName)), [
                new Arg($input),
            ]),
            [],
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
