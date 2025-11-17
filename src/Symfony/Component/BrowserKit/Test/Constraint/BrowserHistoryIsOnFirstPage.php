<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\BrowserKit\Test\Constraint;

use PHPUnit\Framework\Constraint\Constraint;
use Symfony\Component\BrowserKit\AbstractBrowser;

final class BrowserHistoryIsOnFirstPage extends Constraint
{
    public function toString(): string
    {
        return 'is on the first page';
    }

    protected function matches($other): bool
    {
        if (!$other instanceof AbstractBrowser) {
            throw new \LogicException('Can only test on an AbstractBrowser instance.');
        }

        return $other->getHistory()->isFirstPage();
    }

    protected function failureDescription($other): string
    {
        return 'the Browser history '.$this->toString();
    }
}
