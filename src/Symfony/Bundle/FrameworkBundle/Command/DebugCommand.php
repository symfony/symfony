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

use Symfony\Bundle\FrameworkBundle\Debug\DebugApplication;
use Symfony\Bundle\FrameworkBundle\Debug\DebugItem;
use Symfony\Bundle\FrameworkBundle\Debug\DebugResultListWidget;
use Symfony\Bundle\FrameworkBundle\Debug\DebugSectionInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Style\VerticalAlign;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\SelectListWidget;

/**
 * A single command to debug everything: services, routes, config, etc.
 *
 * Run without arguments, it renders a full-screen terminal UI (powered by the
 * Tui component) whose tabs are contributed by services tagged "debug.section".
 * Only the sections whose underlying feature is installed show up, so the
 * command degrades gracefully.
 *
 * Pass a search term and/or one of the per-section options to print the
 * matching item directly instead (this direct mode does not need the Tui
 * component); when several items match, an interactive prompt asks which one
 * to display.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
#[AsCommand(name: 'debug', description: 'Interactively debug the application or search its services, routes, config, …')]
class DebugCommand extends Command
{
    /** @var array<string, DebugSectionInterface>|null */
    private ?array $resolvedSections = null;

    /**
     * @param iterable<string, DebugSectionInterface> $sections     The sections, keyed by machine name and ordered by priority
     * @param list<string>                            $sectionNames The section machine names, known without instantiating the (lazy) sections
     */
    public function __construct(
        private readonly iterable $sections,
        private readonly array $sectionNames = [],
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('query', InputArgument::OPTIONAL, 'A term to search for (across all sections unless a section option is passed)');

        foreach ($this->sectionNames as $name) {
            $this->addOption($name, null, InputOption::VALUE_OPTIONAL, \sprintf('Search only the "%s" section (omit the value to list all of its items)', $name), false);
        }

        $this->setHelp(<<<'EOF'
            The <info>%command.name%</info> command opens a full-screen, interactive UI to debug
            every area of the application (services, routes, config, …):

              <info>php %command.full_name%</info>

            Pass a search term to print the matching item instead. When several items
            match, an interactive prompt asks which one to display:

              <info>php %command.full_name% csrf</info>

            Pass one of the section options to search a single section, or to list all
            of its items when the value is omitted:

              <info>php %command.full_name% --container http_client</info>
              <info>php %command.full_name% --routes user_</info>
              <info>php %command.full_name% --config framework</info>
              <info>php %command.full_name% --routes</info>
            EOF
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $selected = [];
        foreach ($this->sectionNames as $name) {
            if (false !== $value = $input->getOption($name)) {
                $selected[$name] = \is_string($value) ? $value : null;
            }
        }

        if (\count($selected) > 1) {
            $io->getErrorStyle()->error(\sprintf('Pass only one section option at a time (got "--%s").', implode('", "--', array_keys($selected))));

            return Command::INVALID;
        }

        $query = $input->getArgument('query');

        if (!$selected && null === $query) {
            return $this->runInteractiveUi($input, $io);
        }

        $sectionName = $selected ? array_key_first($selected) : null;

        return $this->runDirectSearch($input, $output, $io, $sectionName, null !== $sectionName ? $selected[$sectionName] : null, $query);
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        foreach ($this->sectionNames as $name) {
            if (!$input->mustSuggestOptionValuesFor($name)) {
                continue;
            }

            if (!$section = $this->getSections()[$name] ?? null) {
                return;
            }

            try {
                $items = $section->search($input->getCompletionValue());
            } catch (\Throwable) {
                return;
            }

            $values = [];
            foreach ($items as $item) {
                // suggest only shell-safe labels (no whitespace, no control bytes)
                if (!preg_match('/\s|[\x00-\x1f\x7f]/', $item->label)) {
                    $values[] = $item->label;
                }
            }
            $suggestions->suggestValues($values);

            return;
        }
    }

    /**
     * Runs the full-screen interactive UI (requires the Tui component and a real terminal).
     */
    private function runInteractiveUi(InputInterface $input, SymfonyStyle $io): int
    {
        if (!class_exists(Tui::class)) {
            $io->getErrorStyle()->error('The "debug" command requires the Tui component. Try running "composer require symfony/tui".');

            return Command::FAILURE;
        }

        if (!$input->isInteractive()) {
            $io->getErrorStyle()->error('The interactive UI requires an interactive terminal. Pass a search term or a section option (e.g. "debug --routes app_login") to print the results instead.');

            return Command::INVALID;
        }

        $sections = array_values($this->getSections());

        if (!$sections) {
            $io->warning('No debug sections are available.');

            return Command::SUCCESS;
        }

        (new DebugApplication($sections, $this->createTui(...)))->run();

        return Command::SUCCESS;
    }

    /**
     * Searches one section (or all of them) and prints the matching item; this
     * mode works without the Tui component and without a real terminal.
     */
    private function runDirectSearch(InputInterface $input, OutputInterface $output, SymfonyStyle $io, ?string $sectionName, ?string $sectionQuery, ?string $query): int
    {
        $errorStyle = $io->getErrorStyle();
        $sections = $this->getSections();

        if (!$sections) {
            $io->warning('No debug sections are available.');

            return Command::SUCCESS;
        }

        if (null !== $sectionName && !isset($sections[$sectionName])) {
            $errorStyle->error(\sprintf('The "%s" debug section is not available.', $sectionName));

            return Command::FAILURE;
        }

        // the option value wins; an option with no value falls back to the argument
        // ("debug foo --routes" is the same as "debug --routes foo")
        $query = $sectionQuery ?? $query ?? '';
        $width = max(20, (new Terminal())->getWidth() - 2);

        if (null !== $sectionName) {
            $section = $sections[$sectionName];

            try {
                $results = $this->buildResults([$sectionName => $section], $section->search($query), false);
            } catch (\Throwable $e) {
                $errorStyle->error(\sprintf('Searching the "%s" section failed: %s', $sectionName, $e->getMessage()));

                return Command::FAILURE;
            }
        } else {
            $results = $this->searchAllSections($sections, $query, $input, $output, $errorStyle);
        }

        if (!$results) {
            $errorStyle->error('' === $query ? 'No items found.' : \sprintf('No items match "%s".', $query));

            if (null !== $sectionName && '' !== $query && $alternatives = $this->findAlternatives($sections[$sectionName], $query)) {
                $errorStyle->comment('Did you mean one of these?');
                $errorStyle->listing($alternatives);
            }

            return Command::FAILURE;
        }

        if (1 === \count($results)) {
            $this->printDetail($results[0]['section'], $results[0]['item'], $width, $output);

            return Command::SUCCESS;
        }

        if (!$input->isInteractive()) {
            foreach ($results as $result) {
                $output->writeln($this->sanitizeLabel($result['label']), OutputInterface::OUTPUT_RAW);
            }
            $errorStyle->comment(\sprintf('Found %d matching items; run the command interactively or refine the search term to display one of them.', \count($results)));

            return Command::SUCCESS;
        }

        $choices = [];
        foreach ($results as $result) {
            $label = $this->sanitizeLabel($result['label']);
            if (isset($choices[$label]) && null !== $result['item']->group) {
                $label = \sprintf('%s (%s)', $label, $result['item']->group);
            }
            $unique = $label;
            for ($i = 2; isset($choices[$unique]); ++$i) {
                $unique = \sprintf('%s (%d)', $label, $i);
            }
            $choices[$unique] = $result;
        }

        $answer = $io->choice(\sprintf('Found %d matching items, select the one to display', \count($results)), array_keys($choices));
        $this->printDetail($choices[$answer]['section'], $choices[$answer]['item'], $width, $output);

        return Command::SUCCESS;
    }

    /**
     * @param array<string, DebugSectionInterface> $sections
     *
     * @return list<array{section: DebugSectionInterface, item: DebugItem, label: string}>
     */
    private function searchAllSections(array $sections, string $query, InputInterface $input, OutputInterface $output, SymfonyStyle $errorStyle): array
    {
        $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        $progress = null;
        if ($input->isInteractive() && $errorOutput->isDecorated()) {
            $progress = new ProgressIndicator($errorOutput);
            $progress->start('Searching...');
        }

        $results = [];
        $failures = [];
        foreach ($sections as $name => $section) {
            if ($progress) {
                $progress->setMessage(\sprintf('Searching %s...', $section->getLabel()));
                $progress->advance();
            }

            try {
                $results[] = $this->buildResults([$name => $section], $section->search($query), true);
            } catch (\Throwable $e) {
                $failures[$section->getLabel()] = $e;
            }
        }

        $progress?->finish('Searched all sections.');

        // a broken section must not prevent searching the others
        foreach ($failures as $label => $e) {
            $errorStyle->warning(\sprintf('Searching the "%s" section failed: %s', $label, $e->getMessage()));
            if ($output->isVerbose()) {
                $errorStyle->writeln((string) $e);
            }
        }

        return array_merge(...$results);
    }

    /**
     * @param array<string, DebugSectionInterface> $sections
     * @param list<DebugItem>                      $items
     *
     * @return list<array{section: DebugSectionInterface, item: DebugItem, label: string}>
     */
    private function buildResults(array $sections, array $items, bool $prefixSectionLabel): array
    {
        $section = reset($sections);

        $results = [];
        foreach ($items as $item) {
            $results[] = [
                'section' => $section,
                'item' => $item,
                'label' => $prefixSectionLabel ? \sprintf('%s › %s', $section->getLabel(), $item->label) : $item->label,
            ];
        }

        return $results;
    }

    private function printDetail(DebugSectionInterface $section, DebugItem $item, int $width, OutputInterface $output): void
    {
        $detail = $item->detail ?? $section->describe($item, $width);

        if (!$output->isDecorated()) {
            // strip the raw ANSI codes produced by the section descriptors (CSI
            // sequences and OSC 8 hyperlinks); Helper::removeDecoration() is not
            // used on purpose, it would re-format literal "<...>" text
            $detail = preg_replace('/\e\]8;[^\e\x07]*(?:\e\\\\|\x07)/', '', $detail) ?? $detail;
            $detail = preg_replace('/\e\[[0-9;?]*[ -\/]*[@-~]/', '', $detail) ?? $detail;
        }

        // OUTPUT_RAW is mandatory: the detail may contain "<" characters that the
        // console formatter would misinterpret as formatting tags
        $output->writeln(rtrim($detail, "\n"), OutputInterface::OUTPUT_RAW);
    }

    /**
     * @return list<string>
     */
    private function findAlternatives(DebugSectionInterface $section, string $query): array
    {
        try {
            // the failed search already built and memoized the section index, so this is cheap
            $items = $section->search('');
        } catch (\Throwable) {
            return [];
        }

        $query = strtolower($query);
        $alternatives = [];
        foreach ($items as $item) {
            $label = $this->sanitizeLabel($item->label);
            $distance = levenshtein($query, strtolower($label));
            if ($distance <= \strlen($query) / 3) {
                $alternatives[$label] = $distance;
            }
        }
        asort($alternatives);

        return \array_slice(array_keys($alternatives), 0, 5);
    }

    private function sanitizeLabel(string $label): string
    {
        return preg_replace("/[\x00-\x08\x0b-\x1f\x7f]|\xc2[\x80-\x9f]/", '', $label) ?? '';
    }

    /**
     * @return array<string, DebugSectionInterface>
     */
    private function getSections(): array
    {
        return $this->resolvedSections ??= $this->sections instanceof \Traversable ? iterator_to_array($this->sections) : $this->sections;
    }

    /**
     * Builds the Tui instance. Isolated in its own method so tests can override
     * it to inject a virtual terminal without referencing Tui in the public API.
     */
    protected function createTui(Keybindings $keybindings): Tui
    {
        $styleSheet = new StyleSheet([
            ':root' => new Style(direction: Direction::Vertical, gap: 0, verticalAlign: VerticalAlign::Top),
            DebugResultListWidget::class.'::selected' => new Style(reverse: true),
            DebugResultListWidget::class.'::selected:focus' => new Style(reverse: true),
            SelectListWidget::class.'::selected' => new Style(reverse: true),
            SelectListWidget::class.'::selected:focus' => new Style(reverse: true),
        ]);

        return new Tui(styleSheet: $styleSheet, keybindings: $keybindings);
    }
}
