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
trait ConstraintTraitForV7
{
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

    private function doAdditionalFailureDescription($other): string
    {
        return '';
    }

    private function doCount(): int
    {
        return 1;
    }

    private function doFailureDescription($other): string
    {
        return $this->exporter()->export($other).' '.$this->toString();
    }

    private function doMatches($other): bool
    {
        return false;
    }

    private function doToString(): string
    {
        return '';
    }
}
