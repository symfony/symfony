<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\CI\GithubActionReporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\RouteCollectionAnalyzer;
use Symfony\Component\Routing\RouterInterface;

/**
 * Reports the routes that can never match or that are partly matched by a route declared before them.
 *
 * @final
 */
#[AsCommand(name: 'lint:routing', description: 'Lint the routes and report the shadowed, duplicate and ambiguous ones')]
class RouteLintCommand extends Command
{
    private const FORMATS = ['txt', 'json', 'github'];

    public function __construct(
        private RouterInterface $router,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('format', null, InputOption::VALUE_REQUIRED, \sprintf('The output format ("%s")', implode('", "', self::FORMATS)))
            ->setHelp(<<<'EOF'
                The <info>%command.name%</info> command checks each route against the routes declared before it
                and reports:

                  * <error>errors</error> for routes that can never match, because an earlier route has the same
                    definition or matches all their URLs;
                  * <comment>warnings</comment> for routes partly matched by an earlier route that has placeholders
                    without requirements, for example only for some HTTP methods.

                The command exits with a non-zero code when errors are found:

                  <info>%command.invocation%</info>

                The <info>--format</info> option specifies the format of the command output:

                  <info>%command.invocation% --format=json</info>

                The checks use sample URLs built from the requirements of the routes, so they
                can miss some conflicts.

                EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $format = $input->getOption('format') ?? (GithubActionReporter::isGithubActionEnvironment() ? 'github' : 'txt');

        if (!\in_array($format, self::FORMATS, true)) {
            throw new InvalidArgumentException(\sprintf('Supported formats are "%s".', implode('", "', self::FORMATS)));
        }

        $issues = (new RouteCollectionAnalyzer())->analyze($this->router->getRouteCollection());
        $errors = \count(array_filter($issues, static fn (array $issue) => 'error' === $issue['severity']));

        if ('json' === $format) {
            $io->writeln(json_encode($issues, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));

            return min($errors, 1);
        }

        $githubReporter = 'github' === $format ? new GithubActionReporter($io) : null;

        foreach ($issues as $issue) {
            if ('error' === $issue['severity']) {
                $io->text(\sprintf('<error> ERROR </error> %s', $issue['message']));
                $githubReporter?->error($issue['message']);
            } else {
                $io->text(\sprintf('<comment> WARNING </comment> %s', $issue['message']));
                $githubReporter?->warning($issue['message']);
            }
        }

        if (!$issues) {
            $io->success('No shadowed, duplicate or ambiguous routes found.');
        } elseif ($errors) {
            $io->error(\sprintf('Found %d error(s) and %d warning(s) in the routes.', $errors, \count($issues) - $errors));
        } else {
            $io->warning(\sprintf('Found %d warning(s) in the routes.', \count($issues)));
        }

        return min($errors, 1);
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestOptionValuesFor('format')) {
            $suggestions->suggestValues(self::FORMATS);
        }
    }
}
