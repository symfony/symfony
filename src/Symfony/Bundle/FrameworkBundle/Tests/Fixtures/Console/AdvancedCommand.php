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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;

#[AsCommand('app:advanced')]
class AdvancedCommand
{
    public function __invoke(
        OutputInterface $output,
        // Test #[Autowire] with service reference
        #[Autowire(service: 'Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Console\TestService')]
        TestService $autowiredService,
        // Test #[Autowire] with parameter
        #[Autowire('%kernel.environment%')]
        string $environment,
        // Test #[Target] for named autowiring
        #[Target('test_service')]
        TestService $targetedService,
        // Test regular autowiring
        TestService $regularService,
        #[Argument] string $name = 'default'
    ): int {
        $output->writeln("Autowired: {$autowiredService->getMessage()}");
        $output->writeln("Environment: {$environment}");
        $output->writeln("Targeted: {$targetedService->getMessage()}");
        $output->writeln("Regular: {$regularService->getMessage()}");
        $output->writeln("Name: {$name}");

        return Command::SUCCESS;
    }
}
