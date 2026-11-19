<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Tests\Fixtures;

use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\RefinementTypeInterface;

/**
 * A refinement without a check of its own.
 */
final class DummyRefinementType extends Type implements RefinementTypeInterface
{
    public function getWrappedType(): Type
    {
        return Type::string();
    }

    public function wrappedTypeIsSatisfiedBy(callable $specification): bool
    {
        return $this->getWrappedType()->isSatisfiedBy($specification);
    }

    public function __toString(): string
    {
        return 'non-empty-string';
    }
}
