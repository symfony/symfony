<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tester\Constraint;

use PHPUnit\Framework\Constraint\Constraint;
use Symfony\Component\Console\Command\Command;

final class CommandIsFaulty extends Constraint
{
    public function toString(): string
    {
        return 'is faulty';
    }

    protected function matches($other): bool
    {
        return Command::FAILURE === $other || Command::INVALID === $other;
    }

    protected function failureDescription($other): string
    {
        return 'the command '.$this->toString();
    }

    protected function additionalFailureDescription($other): string
    {
        return Command::SUCCESS === $other
            ? 'Command was successful.'
            : sprintf('Command returned exit status %d.', $other);
    }
}
