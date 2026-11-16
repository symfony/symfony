<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\CI\GithubActionReporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\Parser;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\HttpKernel\CacheWarmer\ExpressionCollector;

/**
 * Lints the expressions of controller attributes and the ones listed in the configuration.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
#[AsCommand(name: 'lint:expressions', description: 'Lint the expressions of controller attributes and the ones listed in the configuration')]
final class ExpressionLintCommand extends Command
{
    /**
     * @param iterable<string, ExpressionLanguage> $expressionLanguages Indexed by their service id
     */
    public function __construct(
        private iterable $expressionLanguages,
        private ExpressionCollector $collector,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'The output format ("txt", "json" or "github")')
            ->setHelp(<<<'EOF'
                The <info>%command.name%</info> command checks the syntax of the expressions held by the attributes of controllers, such as <info>#[IsGranted]</info> or <info>#[Cache]</info>, and of the ones listed in the configuration, such as the <info>allow_if</info> option of <info>access_control</info> rules.

                It also reports the functions that do not exist and, when the expression language tells which ones it provides, the variables that do not exist:

                  <info>%command.invocation%</info>

                Use <info>--format=json</info> to output the result of each expression as JSON:

                  <info>%command.invocation% --format=json</info>

                Use <info>--format=github</info> to emit GitHub Actions error annotations alongside the text report. This format is selected by default in GitHub Actions.
                EOF);
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestOptionValuesFor('format')) {
            $suggestions->suggestValues(['txt', 'json', 'github']);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = $input->getOption('format') ?? (GithubActionReporter::isGithubActionEnvironment() ? 'github' : 'txt');
        if (!\in_array($format, ['txt', 'json', 'github'], true)) {
            throw new InvalidArgumentException('Supported formats are "txt", "json" and "github".');
        }

        $io = new SymfonyStyle($input, $output);
        $count = 0;
        $results = [];

        foreach ($this->expressionLanguages as $id => $expressionLanguage) {
            foreach ([...$this->collector->getAttributeExpressions($id), ...$this->collector->getListedExpressions($id)] as $collected) {
                ++$count;
                $result = ['source' => $collected->source, 'expression' => (string) $collected->expression, 'valid' => true];

                try {
                    $expressionLanguage->lint($collected->expression, $collected->variables ?? [], null === $collected->variables ? Parser::IGNORE_UNKNOWN_VARIABLES : 0);
                } catch (SyntaxError $e) {
                    $result['valid'] = false;
                    $result['message'] = $e->getMessage();
                }
                $results[] = $result;
            }
        }

        foreach ($this->collector->getErrors() as $source => $error) {
            $results[] = ['source' => $source, 'expression' => null, 'valid' => false, 'message' => $error->getMessage()];
        }

        $errors = array_filter($results, static fn (array $result) => !$result['valid']);

        if ('json' === $format) {
            $output->writeln(json_encode($results, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_INVALID_UTF8_SUBSTITUTE), OutputInterface::OUTPUT_RAW);

            return $errors ? self::FAILURE : self::SUCCESS;
        }

        $githubReporter = 'github' === $format ? new GithubActionReporter($output) : null;
        foreach ($errors as $error) {
            $io->text('<error> ERROR </error> in '.OutputFormatter::escape($error['source']));
            $io->text(\sprintf('<error> >> %s</error>', OutputFormatter::escape($error['message'])));
            $githubReporter?->error($error['source'].': '.$error['message']);
        }

        if (!$errors) {
            $io->success(\sprintf('All %d expressions are valid.', $count));

            return self::SUCCESS;
        }

        $io->error(\sprintf('Found %d error%s while linting %d expressions.', \count($errors), 1 === \count($errors) ? '' : 's', $count));

        return self::FAILURE;
    }
}
