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

use Symfony\Bundle\SecurityBundle\Debug\DebugRoleHierarchy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

#[AsCommand(name: 'debug:roles', description: 'Debug the role hierarchy configuration.')]
final class DebugRolesCommand extends Command
{
    public function __construct(private readonly RoleHierarchyInterface $roleHierarchy)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp(<<<EOF
This <info>%command.name%</info> command display the current role hierarchy:

    <info>php %command.full_name%</info>

You can pass one or multiple role names to display the effective roles:

    <info>php %command.full_name% ROLE_USER</info>

To get a tree view of the inheritance, use the <info>tree</info> option:

    <info>php %command.full_name% --tree</info>
    <info>php %command.full_name% ROLE_USER --tree</info>

<comment>Note:</comment> With a custom implementation for <info>security.role_hierarchy</info>, the <info>--tree</info> option is ignored and the <info>roles</info> argument is required.

EOF
        )
            ->setDefinition([
            new InputArgument('roles', ($this->isBuiltInRoleHierarchy() ? InputArgument::OPTIONAL : InputArgument::REQUIRED) | InputArgument::IS_ARRAY, 'The role(s) to resolve'),
            new InputOption('tree', 't', InputOption::VALUE_NONE, 'Show the hierarchy in a tree view'),
        ]);
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        if (!$this->isBuiltInRoleHierarchy()) {
            $io = new SymfonyStyle($input, $output);

            if ($input->getOption('tree')) {
                $io->warning('Ignoring option "--tree" because of a custom role hierarchy implementation.');
                $input->setOption('tree', null);
            }
        }
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        if (!$this->isBuiltInRoleHierarchy() && empty($input->getArgument('roles'))) {
            $io = new SymfonyStyle($input, $output);

            $roles[] = $io->ask('Enter a role to debug', validator: function (?string $role) {
                $role = trim($role);
                if (empty($role)) {
                    throw new \RuntimeException('You must enter a non empty role name.');
                }

                return $role;
            });
            while ($role = trim($io->ask('Add another role? (press enter to skip)') ?? '')) {
                $roles[] = $role;
            }

            $input->setArgument('roles', $roles);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $roles = $input->getArgument('roles');

        if (empty($roles)) {
            // Full configuration output
            $io->title('Current role hierarchy configuration:');

            if ($input->getOption('tree')) {
                $this->outputTree($io, $this->getBuiltInDebugHierarchy()->getHierarchy());
            } else {
                $this->outputMap($io, $this->getBuiltInDebugHierarchy()->getMap());
            }

            $io->comment('To show reachable roles for a given role, re-run this command with role names. (e.g. <comment>debug:roles ROLE_USER</comment>)');

            return self::SUCCESS;
        }

        // Matching roles output
        $io->title(sprintf('Effective roles for %s:', implode(', ', array_map(fn ($v) => sprintf('<info>%s</info>', $v), $roles))));

        if ($input->getOption('tree')) {
            $this->outputTree($io, $this->getBuiltInDebugHierarchy()->getHierarchy($roles));
        } else {
            $io->listing($this->roleHierarchy->getReachableRoleNames($roles));
        }

        return self::SUCCESS;
    }

    private function outputMap(OutputInterface $output, array $map): void
    {
        foreach ($map as $main => $roles) {
            if ($this->getBuiltInDebugHierarchy()->isPlaceholder($main)) {
                $main = $this->stylePlaceholder($main);
            }

            $output->writeln(sprintf('%s:', $main));
            foreach ($roles as $r) {
                $output->writeln(sprintf('  - %s', $r));
            }
            $output->writeln('');
        }
    }

    private function outputTree(OutputInterface $output, array $tree): void
    {
        foreach ($tree as $role => $hierarchy) {
            $output->writeln($this->generateTree($role, $hierarchy));
            $output->writeln('');
        }
    }

    /**
     * Generates a tree representation, line by line, in the tree unix style.
     *
     * Example output:
     *
     *     ROLE_A
     *     └── ROLE_B
     *
     *     ROLE_C
     *     ├── ROLE_A
     *     │   └── ROLE_B
     *     └── ROLE_D
     */
    private function generateTree(string $name, array $tree, string $indent = '', bool $last = true, bool $root = true): \Generator
    {
        if ($this->getBuiltInDebugHierarchy()->isPlaceholder($name)) {
            $name = $this->stylePlaceholder($name);
        }

        if ($root) {
            // Yield root node as it is
            yield $name;
        } else {
            // Generate line in the tree:
            // Line: [indent]├── [name]
            // Last line: [indent]└── [name]
            yield sprintf('%s%s%s %s', $indent, $last ? "\u{2514}" : "\u{251c}", str_repeat("\u{2500}", 2), $name);

            // Update indent for next nested:
            // Append "|   " for a nested tree
            // Append "    " for last nested tree
            $indent .= ($last ? ' ' : "\u{2502}").str_repeat(' ', 3);
        }

        $i = 0;
        $count = \count($tree);
        foreach ($tree as $key => $value) {
            yield from $this->generateTree($key, $value, $indent, $i === $count - 1, false);
            ++$i;
        }
    }

    private function stylePlaceholder(string $role): string
    {
        return sprintf('<info>%s</info>', $role);
    }

    private function isBuiltInRoleHierarchy(): bool
    {
        return $this->roleHierarchy instanceof DebugRoleHierarchy;
    }

    private function getBuiltInDebugHierarchy(): DebugRoleHierarchy
    {
        if (!$this->roleHierarchy instanceof DebugRoleHierarchy) {
            throw new \LogicException('Cannot use the built-in debug hierarchy with a custom implementation.');
        }

        return $this->roleHierarchy;
    }
}
