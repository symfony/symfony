<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\TypedReference;

/**
 * @internal
 */
final class AutowiringFailedReference extends TypedReference
{
    private ?\Closure $alternatives = null;

    public function __construct(TypedReference $reference, \Closure $alternatives)
    {
        parent::__construct((string) $reference, $reference->getType(), $reference->getInvalidBehavior(), $reference->getName(), $reference->getAttributes());
        $this->alternatives = $alternatives;
    }

    public function getAlternatives(): string
    {
        return null !== $this->alternatives ? ($this->alternatives)() : '';
    }

    public function __serialize(): array
    {
        return [(string) $this, $this->getType(), $this->getInvalidBehavior(), $this->getName(), $this->getAttributes()];
    }

    public function __unserialize(array $data): void
    {
        parent::__construct(...$data);
    }
}
