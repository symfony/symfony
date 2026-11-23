<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Translation\Exception\ExceptionInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Lint translations files syntax and outputs encountered errors.
 *
 * @author Hugo Alliaume <hugo@alliau.me>
 */
#[AsCommand(name: 'lint:translations', description: 'Lint translations files syntax and outputs encountered errors')]
class TranslationLintCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private TranslatorInterface&TranslatorBagInterface $translator,
        private array $enabledLocales = [],
    ) {
        $this->enabledLocales = array_filter($enabledLocales);
        parent::__construct();
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestOptionValuesFor('locale')) {
            $suggestions->suggestValues($this->enabledLocales);
        }
    }

    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputOption('locale', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Specify the locales to lint.', $this->enabledLocales),
                new InputOption('check-mixed-alphabets', null, InputOption::VALUE_NONE, 'Also report the words that mix letters from different alphabets (requires the intl extension).'),
            ])
            ->setHelp(<<<'EOF'
                The <info>%command.name%</> command lint translations.

                  <info>%command.invocation%</>

                The <info>--check-mixed-alphabets</> option also reports the words that mix letters from different alphabets, like "сookie" written with a Cyrillic "с":

                  <info>%command.invocation% --check-mixed-alphabets</>
                EOF
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $locales = $input->getOption('locale');
        $spoofchecker = null;

        if ($input->getOption('check-mixed-alphabets')) {
            if (!class_exists(\Spoofchecker::class)) {
                throw new LogicException('The "--check-mixed-alphabets" option requires the "intl" PHP extension.');
            }

            // SINGLE_SCRIPT is PHP's name for ICU's restriction-level check (UTS #39, section 5.2)
            $spoofchecker = new \Spoofchecker();
            $spoofchecker->setChecks(\Spoofchecker::SINGLE_SCRIPT);
            $spoofchecker->setRestrictionLevel(\Spoofchecker::HIGHLY_RESTRICTIVE);
        }

        /** @var array<string, array<string, array<string, string[]>>> $errors */
        $errors = [];
        $domainsByLocales = [];

        foreach ($locales as $locale) {
            $messageCatalogue = $this->translator->getCatalogue($locale);

            foreach ($domainsByLocales[$locale] = $messageCatalogue->getDomains() as $domain) {
                foreach ($messageCatalogue->all($domain) as $id => $translation) {
                    try {
                        $this->translator->trans($id, [], $domain, $messageCatalogue->getLocale());
                    } catch (ExceptionInterface $e) {
                        $errors[$locale][$domain][$id][] = $e->getMessage();
                    }

                    foreach ($spoofchecker ? self::findMixedAlphabetWords($spoofchecker, $translation) : [] as $word => $alphabets) {
                        $errors[$locale][$domain][$id][] = \sprintf('The word "%s" mixes letters from different alphabets: %s.', $word, $alphabets);
                    }
                }
            }
        }

        if (!$domainsByLocales) {
            $this->io->error('No translation files were found.');

            return Command::SUCCESS;
        }

        $this->io->table(
            ['Locale', 'Domains', 'Valid?'],
            array_map(
                static fn (string $locale, array $domains) => [
                    OutputFormatter::escape($locale),
                    implode(', ', array_map(OutputFormatter::escape(...), $domains)),
                    !\array_key_exists($locale, $errors) ? '<info>Yes</>' : '<error>No</>',
                ],
                array_keys($domainsByLocales),
                $domainsByLocales
            ),
        );

        if ($errors) {
            foreach ($errors as $locale => $domains) {
                foreach ($domains as $domain => $domainsErrors) {
                    $this->io->section(\sprintf('Errors for locale "%s" and domain "%s"', OutputFormatter::escape($locale), OutputFormatter::escape($domain)));

                    foreach ($domainsErrors as $id => $messages) {
                        $this->io->text(\sprintf('Translation key "%s" is invalid:', OutputFormatter::escape($id)));

                        foreach ($messages as $message) {
                            $this->io->error($message);
                        }
                    }
                }
            }

            return Command::FAILURE;
        }

        $this->io->success('All translations are valid.');

        return Command::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private static function findMixedAlphabetWords(\Spoofchecker $spoofchecker, string $message): array
    {
        $text = preg_replace(['{</?[a-zA-Z][^<>]*+>}', '/%[^%\s]++%/'], ' ', self::removeIcuSyntax($message));

        if (!preg_match_all('/[\p{L}\p{M}]++/u', $text, $words)) {
            return [];
        }

        $mixedAlphabetWords = [];

        foreach ($words[0] as $word) {
            if (isset($mixedAlphabetWords[$word]) || !$spoofchecker->isSuspicious($word)) {
                continue;
            }

            $letters = [];
            foreach (mb_str_split($word) as $char) {
                $script = \IntlChar::getPropertyValueName(\IntlChar::PROPERTY_SCRIPT, \IntlChar::getIntPropertyValue($char, \IntlChar::PROPERTY_SCRIPT));

                if ('Common' !== $script && 'Inherited' !== $script) {
                    $letters[$script] = ($letters[$script] ?? '').$char;
                }
            }

            $mixedAlphabetWords[$word] = implode(', ', array_map(static fn ($script, $chars) => \sprintf('%s "%s"', $script, $chars), array_keys($letters), $letters));
        }

        return $mixedAlphabetWords;
    }

    /**
     * Replaces the arguments and the syntax of an ICU message by spaces, keeping the text of plural and select cases.
     */
    private static function removeIcuSyntax(string $message): string
    {
        preg_match_all("/'(?:'|[{}](?:[^']|'')*+'?)|[{},]|[^{},']++|'/", $message, $tokens);

        $text = '';
        $type = '';
        $states = ['text'];

        foreach ($tokens[0] as $token) {
            $i = array_key_last($states);
            $state = $states[$i];

            if ('{' === $token) {
                $states[] = match ($state) {
                    'text' => 'name',
                    'cases' => 'text',
                    default => 'style',
                };
            } elseif ('}' === $token) {
                if ($i) {
                    array_pop($states);
                }
            } elseif ('text' === $state) {
                $text .= $token;

                continue;
            } elseif (',' === $token && 'name' === $state) {
                $states[$i] = 'type';
            } elseif (',' === $token && 'type' === $state) {
                $states[$i] = \in_array($type, ['plural', 'select', 'selectordinal'], true) ? 'cases' : 'style';
            } elseif ('type' === $state) {
                $type = strtolower(trim($token));
            }

            $text .= ' ';
        }

        return $text;
    }
}
