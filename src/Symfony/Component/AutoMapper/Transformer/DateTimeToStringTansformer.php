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
use PhpParser\Node\Scalar\String_;
use Symfony\Component\AutoMapper\Extractor\PropertyMapping;
use Symfony\Component\AutoMapper\Generator\UniqueVariableScope;

/**
 * Transform a \DateTimeInterface object to a string.
 *
 * @expiremental in 4.3
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
final class DateTimeToStringTansformer implements TransformerInterface
{
    private $format;

    public function __construct(string $format = \DateTimeInterface::RFC3339)
    {
        $this->format = $format;
    }

    /**
     * {@inheritdoc}
     */
    public function transform(Expr $input, PropertyMapping $propertyMapping, UniqueVariableScope $uniqueVariableScope): array
    {
        return [new Expr\MethodCall($input, 'format', [
            new Arg(new String_($this->format)),
        ]), []];
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
