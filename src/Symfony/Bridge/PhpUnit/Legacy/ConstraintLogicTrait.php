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
trait ConstraintLogicTrait
{
    private function doEvaluate($other, $description, $returnResult)
    {
        $success = false;

        if ($this->matches($other)) {
            $success = true;
        }

        if ($returnResult) {
            return $success;
        }

        if (!$success) {
            $this->fail($other, $description);
        }

        return null;
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
