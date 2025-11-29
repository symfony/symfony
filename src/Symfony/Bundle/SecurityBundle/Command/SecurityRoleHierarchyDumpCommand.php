<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\Dumper\MermaidDirection;
use Symfony\Component\Security\Core\Dumper\MermaidDumper;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * Command to dump the role hierarchy as a Mermaid flowchart.
 *
 * @author Damien Fernandes <damien.fernandes24@gmail.com>
 */
#[AsCommand(
    name: 'debug:security:role-hierarchy',
    description: 'Dump the role hierarchy as a Mermaid flowchart',
    help: <<<'USAGE'
        The <info>%command.name%</info> command dumps the role hierarchy in Mermaid format.

        <info>Mermaid</info>: %command.full_name% > roles.mmd
        <info>Mermaid with direction</info>: %command.full_name% --direction=BT > roles.mmd
        USAGE
)]
class SecurityRoleHierarchyDumpCommand
{
    public function __construct(
        private readonly RoleHierarchyInterface $roleHierarchy,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'The direction of the flowchart', suggestedValues: [self::class, 'getMermaidDirectionChoices'])] MermaidDirection $direction = MermaidDirection::TOP_TO_BOTTOM,
    ): int {
        $dumper = new MermaidDumper();

        foreach (explode("\n", $dumper->dump($this->roleHierarchy, $direction)) as $line) {
            $io->writeln($line, OutputInterface::OUTPUT_RAW);
        }

        return 0;
    }

    public static function getMermaidDirectionChoices(): array
    {
        return array_column(MermaidDirection::cases(), 'value');
    }
}
