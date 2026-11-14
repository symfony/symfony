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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Exception\InvalidArgumentException;
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
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'The output format ("txt" or "json")', 'txt')
            ->setHelp(<<<'EOF'
                The <info>%command.name%</info> command checks the syntax of the expressions held by the attributes of controllers, such as <info>#[IsGranted]</info> or <info>#[Cache]</info>, and of the ones listed in the configuration, such as the <info>allow_if</info> option of <info>access_control</info> rules.

                It also reports the functions that do not exist and, when the expression language tells which ones it provides, the variables that do not exist:

                  <info>%command.invocation%</info>

                Use <info>--format=json</info> to output errors as JSON:

                  <info>%command.invocation% --format=json</info>
                EOF);
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestOptionValuesFor('format')) {
            $suggestions->suggestValues(['txt', 'json']);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = $input->getOption('format');
        if (!\in_array($format, ['txt', 'json'], true)) {
            throw new InvalidArgumentException('Supported formats are "txt" and "json".');
        }

        $io = new SymfonyStyle($input, $output);
        $count = 0;
        $errors = [];

        foreach ($this->expressionLanguages as $id => $expressionLanguage) {
            foreach ([...$this->collector->getAttributeExpressions($id), ...$this->collector->getListedExpressions($id)] as $collected) {
                ++$count;

                try {
                    $expressionLanguage->lint($collected->expression, $collected->variables ?? [], null === $collected->variables ? Parser::IGNORE_UNKNOWN_VARIABLES : 0);
                } catch (SyntaxError $e) {
                    $errors[] = [$collected->source, $e->getMessage()];
                }
            }
        }

        foreach ($this->collector->getErrors() as $source => $error) {
            $errors[] = [$source, $error->getMessage()];
        }

        if ('json' === $format) {
            $output->writeln(json_encode(array_map(static fn (array $error) => ['source' => $error[0], 'message' => $error[1]], $errors), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_INVALID_UTF8_SUBSTITUTE), OutputInterface::OUTPUT_RAW);

            return $errors ? self::FAILURE : self::SUCCESS;
        }

        foreach ($errors as [$source, $error]) {
            $io->text('<error> ERROR </error> in '.$source);
            $io->text(\sprintf('<error> >> %s</error>', $error));
        }

        if (!$errors) {
            $io->success(\sprintf('All %d expressions are valid.', $count));

            return self::SUCCESS;
        }

        $io->error(\sprintf('Found %d error%s while linting %d expressions.', \count($errors), 1 === \count($errors) ? '' : 's', $count));

        return self::FAILURE;
    }
}
