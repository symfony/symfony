<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Test\Constraint;

use PHPUnit\Framework\Constraint\Constraint;

/**
 * @author Marilena Ruffelaere <marilena.ruffelaere@gmail.com>
 */
final class MessageCount extends Constraint
{
    public function __construct(private readonly int $expectedValue, private readonly ?string $bus = null)
    {
    }

    public function toString(): string
    {
        return sprintf('%s has sent "%d" messages', $this->bus ? $this->bus.' ' : '', $this->expectedValue);
    }


    protected function matches($other): bool
    {
        return $this->expectedValue === \count($other);
    }


    protected function failureDescription($other): string
    {
        return sprintf('the Bus %s (%d sent)', $this->toString(), \count($other));
    }
}
