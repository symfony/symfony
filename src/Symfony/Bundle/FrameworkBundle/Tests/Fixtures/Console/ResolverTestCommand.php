<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Console;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\ValueResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Tests both auto-tagged and targeted value resolver autoconfiguration.
 */
#[AsCommand('app:resolver-test')]
class ResolverTestCommand
{
    public function __invoke(
        OutputInterface $output,
        #[Argument] string $scenario,
        string $autoTagged = '',
        #[ValueResolver('targeted')] ?CustomType $targeted = null,
    ): int {
        if ('auto-tagged' === $scenario) {
            $output->writeln('Auto-tagged: '.$autoTagged);
        } elseif ('targeted' === $scenario && $targeted) {
            $output->writeln('Targeted: '.$targeted->value);
        }

        return Command::SUCCESS;
    }
}
