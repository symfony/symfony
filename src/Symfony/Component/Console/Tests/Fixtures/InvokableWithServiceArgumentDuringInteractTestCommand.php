<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Fixtures;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Interact;
use Symfony\Component\Console\Command\Command;

#[AsCommand('test:method')]
class InvokableWithServiceArgumentDuringInteractTestCommand
{
    // Requiring the service here forces argument resolution to run during Command::interact(),
    // before the command has ever been validated or invoked.
    #[Interact]
    public function before(\stdClass $s): void
    {
    }

    public function __invoke(\stdClass $s): int
    {
        return Command::SUCCESS;
    }
}
