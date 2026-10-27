<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Bundle\FrameworkBundle\Console\Descriptor\Descriptor;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ContainerBuilderDebugDumpPass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * A console command for autoconfiguration information.
 *
 * @internal
 */
#[AsCommand(name: 'debug:autoconfiguration', description: 'List classes/interfaces and attributes used for autoconfiguration')]
final class DebugAutoconfigurationCommand extends ContainerDebugCommand
{
    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('search', InputArgument::OPTIONAL, 'A search filter'),
            ])
            ->setHelp(<<<'EOF'
                The <info>%command.name%</info> command lists the classes and interfaces that
                autoconfiguration applies to, with the tags, calls and bindings they add, and the
                attributes that autoconfiguration reads:

                  <info>php %command.full_name%</info>

                You can also pass a search term to filter the list, for example by tag name:

                  <info>php %command.full_name% kernel.reset</info>

                EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        ['instanceof' => $rules, 'attributes' => $attributes] = $this->getAutoconfiguration();
        uksort($rules, 'strnatcmp');
        usort($attributes, 'strnatcmp');

        if ($search = $input->getArgument('search')) {
            $matches = static fn (string $name) => false !== stripos($name, $search);
            $rules = array_filter($rules, static fn (array $rule, string $type) => $matches($type) || array_filter([...$rule['tags'], ...$rule['calls'], ...$rule['bindings']], $matches), \ARRAY_FILTER_USE_BOTH);
            $attributes = array_filter($attributes, $matches);

            if (!$rules && !$attributes) {
                $io->getErrorStyle()->error(\sprintf('No autoconfiguration rules found matching "%s"', $search));

                return 1;
            }
        }

        $io->title('Autoconfiguration');

        if ($search) {
            $io->text(\sprintf('(only showing rules matching <comment>%s</comment>)', $search));
            $io->newLine();
        }

        if ($rules) {
            $io->text('An autoconfigured service gets the configuration listed under each class or interface that its class extends or implements.');

            foreach ($rules as $type => $rule) {
                $text = self::describeClass($type);
                foreach ($rule['tags'] as $tag) {
                    $text[] = \sprintf('  tag <fg=cyan>%s</>', $tag);
                }
                foreach ($rule['calls'] as $method) {
                    $text[] = \sprintf('  call <fg=cyan>%s()</>', $method);
                }
                foreach ($rule['bindings'] as $binding) {
                    $text[] = \sprintf('  bind <fg=cyan>%s</>', $binding);
                }
                $io->text($text);
            }
            $io->newLine();
        }

        if ($attributes) {
            $io->text('An autoconfigured service is also configured by the following attributes, when they are used on its class or on one of its members.');

            foreach ($attributes as $attribute) {
                $io->text(self::describeClass($attribute));
            }
            $io->newLine();
        }

        return 0;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('search')) {
            ['instanceof' => $rules, 'attributes' => $attributes] = $this->getAutoconfiguration();

            $suggestions->suggestValues([...array_keys($rules), ...$attributes]);
        }
    }

    private function getAutoconfiguration(): array
    {
        $container = $this->getContainerBuilder($this->getApplication()->getKernel());

        if ($container->hasParameter('.debug.container.autoconfiguration')) {
            return $container->getParameterBag()->unescapeValue($container->getParameter('.debug.container.autoconfiguration'));
        }

        return ContainerBuilderDebugDumpPass::getAutoconfiguration($container);
    }

    /**
     * @return list<string>
     */
    private static function describeClass(string $class): array
    {
        $text = ['', \sprintf('<fg=yellow>%s</>', $class)];

        // loading a deprecated class to read its description must not report its deprecation
        set_error_handler(static fn () => true, \E_DEPRECATED | \E_USER_DEPRECATED);
        try {
            $description = Descriptor::getClassDescription($class);
        } finally {
            restore_error_handler();
        }

        if ('' !== $description) {
            $text[] = '  '.$description;
        }

        return $text;
    }
}
