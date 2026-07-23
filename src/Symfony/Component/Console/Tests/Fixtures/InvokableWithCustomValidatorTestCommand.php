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

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Ask;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:custom-validator')]
class InvokableWithCustomValidatorTestCommand
{
    public function __invoke(
        OutputInterface $output,
        #[Argument]
        #[Ask('Enter a value:', validator: [self::class, 'validate'])]
        string $value,
    ): int {
        $output->writeln('Value: '.$value);

        return Command::SUCCESS;
    }

    public static function validate(string $value): string
    {
        if ('valid' !== $value) {
            throw new \RuntimeException('Value must be "valid"');
        }

        return $value;
    }
}
