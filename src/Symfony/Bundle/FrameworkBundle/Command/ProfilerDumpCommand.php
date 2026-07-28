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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\Profiler\ProfileDumper;
use Symfony\Component\HttpKernel\Profiler\Profiler;

/**
 * Get profiler information in machine-readable formats.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * @final
 */
#[AsCommand(name: 'profiler:dump', description: 'Get profiler information for a given profile in Markdown or JSON format')]
class ProfilerDumpCommand extends Command
{
    public function __construct(
        private Profiler $profiler,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('token', InputArgument::OPTIONAL, 'The profile token', 'latest'),
                new InputOption('list', null, InputOption::VALUE_NONE, 'List recent profiles instead of dumping one'),
                new InputOption('panel', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Dump only the given panel(s), e.g. "db" or "logger"'),
                new InputOption('type', null, InputOption::VALUE_REQUIRED, 'The profile type ("request" or "command") [default: "request"]'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, \sprintf('The output format ("%s")', implode('", "', $this->getAvailableFormatOptions())), 'md'),
                new InputOption('full', null, InputOption::VALUE_NONE, 'Do not truncate long values in the output'),
                new InputOption('limit', null, InputOption::VALUE_REQUIRED, 'The maximum number of profiles to list', 20),
                new InputOption('url', null, InputOption::VALUE_REQUIRED, 'Filter profiles by URL'),
                new InputOption('method', null, InputOption::VALUE_REQUIRED, 'Filter profiles by HTTP method'),
                new InputOption('status-code', null, InputOption::VALUE_REQUIRED, 'Filter profiles by HTTP status code'),
            ])
            ->setHelp(<<<'EOF'
                The <info>%command.name%</info> command dumps the data collected by the profiler
                for a given profile in a format suitable for terminal consumers such as AI agents
                (long values are truncated in the output unless the <info>--full</info> option is used):

                  <info>php %command.full_name%</info>                   # dump the most recent HTTP profile
                  <info>php %command.full_name% dd93a8</info>            # dump the profile for the given token
                  <info>php %command.full_name% --panel=db</info>        # dump only the database panel
                  <info>php %command.full_name% --status-code=500</info> # dump the most recent failed request
                  <info>php %command.full_name% --type=command</info>    # dump the most recent profiled console command
                  <info>php %command.full_name% --format=json</info>     # machine-readable output
                  <info>php %command.full_name% --panel=request --full</info> # dump the request panel without truncating values

                Use the <info>--list</info> option to list the most recent profiles:

                  <info>php %command.full_name% --list</info>
                  <info>php %command.full_name% --list --limit=50 --method=POST</info>

                Beware that the output may contain sensitive data collected from the requests
                (headers, cookies, query parameters, etc.).
                EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $format = $input->getOption('format');
        if (!\in_array($format, $this->getAvailableFormatOptions(), true)) {
            throw new InvalidArgumentException(\sprintf('Supported formats are "%s".', implode('", "', $this->getAvailableFormatOptions())));
        }

        if ($input->getOption('list')) {
            return $this->listProfiles($input, $output, $io, $format);
        }

        $type = $input->getOption('type') ?? 'request';
        $token = $input->getArgument('token');
        if ('latest' === $token) {
            $latest = current($this->profiler->find(null, $input->getOption('url'), 1, $input->getOption('method'), null, null, $input->getOption('status-code'), static fn (array $profile) => $type === ($profile['virtual_type'] ?? 'request')));
            if (!$latest) {
                $io->error(\sprintf('No profiles found for type "%s". Make some requests to the application first, or profile console commands by running them with the --profile option.', $type));

                return Command::FAILURE;
            }

            $token = $latest['token'];
        }

        if (!$profile = $this->profiler->loadProfile($token)) {
            $io->error(\sprintf('No profile found for token "%s". Run this command with the --list option to list the available profiles.', $token));

            return Command::FAILURE;
        }

        $panels = $input->getOption('panel') ?: null;
        $dumper = $input->getOption('full') ? new ProfileDumper(\PHP_INT_MAX, \PHP_INT_MAX, \PHP_INT_MAX, \PHP_INT_MAX) : new ProfileDumper();

        try {
            $dump = 'json' === $format
                ? json_encode($dumper->toArray($profile, $panels), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_INVALID_UTF8_SUBSTITUTE)
                : $dumper->toMarkdown($profile, $panels);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $output->writeln($dump, OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('token')) {
            $suggestions->suggestValue('latest');
            foreach ($this->profiler->find(null, null, 20, null, null, null) as $profile) {
                $suggestions->suggestValue($profile['token']);
            }
        }

        if ($input->mustSuggestOptionValuesFor('format')) {
            $suggestions->suggestValues($this->getAvailableFormatOptions());
        }

        if ($input->mustSuggestOptionValuesFor('type')) {
            $suggestions->suggestValues(['request', 'command']);
        }
    }

    private function listProfiles(InputInterface $input, OutputInterface $output, SymfonyStyle $io, string $format): int
    {
        $type = $input->getOption('type');
        $profiles = $this->profiler->find(
            null,
            $input->getOption('url'),
            $input->getOption('limit'),
            $input->getOption('method'),
            null,
            null,
            $input->getOption('status-code'),
            null !== $type ? static fn (array $profile) => $type === ($profile['virtual_type'] ?? 'request') : null,
        );

        $profiles = array_map(static fn (array $profile) => [
            'token' => $profile['token'],
            'time' => $profile['time'] ? \DateTimeImmutable::createFromFormat('U', (string) $profile['time'])->format(\DateTimeInterface::RFC3339) : null,
            'method' => $profile['method'],
            'url' => $profile['url'],
            'status_code' => $profile['status_code'] ?? null,
            'type' => $profile['virtual_type'] ?? 'request',
            'has_errors' => (bool) ($profile['has_errors'] ?? false),
        ], $profiles);

        if ('json' === $format) {
            $output->writeln(json_encode($profiles, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_INVALID_UTF8_SUBSTITUTE), OutputInterface::OUTPUT_RAW);

            return Command::SUCCESS;
        }

        if (!$profiles) {
            $io->error('No profiles found. Make some requests to the application first, or profile console commands by running them with the --profile option.');

            return Command::FAILURE;
        }

        $rows = ['| TOKEN | TIME | METHOD | URL | STATUS | TYPE | ERRORS |', '| ----- | ---- | ------ | --- | ------ | ---- | ------ |'];
        foreach ($profiles as $profile) {
            $rows[] = \sprintf('| %s | %s | %s | %s | %s | %s | %s |', $profile['token'], $profile['time'] ?? 'n/a', $profile['method'], $profile['url'], $profile['status_code'] ?? 'n/a', $profile['type'], $profile['has_errors'] ? 'yes' : 'no');
        }
        $rows[] = '';
        $rows[] = 'Run "profiler:dump <token>" to inspect any of these profiles.';

        $output->writeln($rows, OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }

    /** @return string[] */
    private function getAvailableFormatOptions(): array
    {
        return ['md', 'json'];
    }
}
