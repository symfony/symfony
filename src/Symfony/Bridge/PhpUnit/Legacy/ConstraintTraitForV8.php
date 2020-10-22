<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Legacy;

/**
 * @internal
 */
trait ConstraintTraitForV8
{
    use ConstraintLogicTrait;

    /**
     * @return bool|null
     */
    public function evaluate($other, $description = '', $returnResult = false)
    {
        return $this->doEvaluate($other, $description, $returnResult);
    }

    public function count(): int
    {
        return $this->doCount();
    }

    public function toString(): string
    {
        return $this->doToString();
    }

    protected function additionalFailureDescription($other): string
    {
        return $this->doAdditionalFailureDescription($other);
    }

    protected function failureDescription($other): string
    {
        return $this->doFailureDescription($other);
    }

    protected function matches($other): bool
    {
        return $this->doMatches($other);
    }
}
