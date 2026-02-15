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
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Attribute\ValueResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:custom-type', description: 'Tests custom argument resolver')]
class CustomTypeCommand
{
    public function __invoke(
        OutputInterface $output,
        #[ValueResolver(CustomTypeValueResolver::class)] CustomType $custom,
        #[Argument] string $name,
        TestService $service,
        #[ValueResolver(CustomOptionValueResolver::class)] CustomType $customOption,
        #[Argument] int $count = 5,
        #[Argument] TestEnum $status = TestEnum::Active,
        #[Option] string $format = 'json',
        #[Option] ?\DateTime $date = null,
    ): int {
        $output->writeln('CustomType value: '.$custom->value);
        $output->writeln('Name: '.$name);
        $output->writeln('Count: '.$count);
        $output->writeln('Status: '.$status->value);
        $output->writeln('Date: '.($date ?? new \DateTime())->format('Y-m-d'));
        $output->writeln('Service: '.$service->getMessage());
        $output->writeln('Format: '.$format);
        $output->writeln('CustomOption: '.$customOption->value);

        return Command::SUCCESS;
    }
}
