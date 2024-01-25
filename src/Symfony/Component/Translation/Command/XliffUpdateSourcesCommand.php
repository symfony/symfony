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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MetadataAwareInterface;
use Symfony\Component\Translation\Reader\TranslationReaderInterface;
use Symfony\Component\Translation\Writer\TranslationWriterInterface;

/**
 * @author Nicolas Rigaud <squrious@protonmail.com>
 */
#[AsCommand(name: 'translation:update-xliff-sources', description: 'Update source tags with default locale targets in XLIFF files.')]
class XliffUpdateSourcesCommand extends Command
{
    use TranslationTrait;

    private const FORMATS = [
        'xlf12' => ['xlf', '1.2'],
        'xlf20' => ['xlf', '2.0'],
    ];

    public function __construct(
        private readonly TranslationWriterInterface $writer,
        private readonly TranslationReaderInterface $reader,
        private readonly string $defaultLocale,
        private readonly array $transPaths = [],
        private readonly array $enabledLocales = [],
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('paths', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Paths to look for translations.'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'Override the default output format.', 'xlf12'),
                new InputOption('domains', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Specify the domain to update.'),
                new InputOption('locales', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Specify the locale to update.', $this->enabledLocales),
            ])
            ->setHelp(<<<EOF
The <info>%command.name%</info> command updates the source tags in XLIFF files with the default locale translation if available.

You can specify directories to update in the command arguments:

  <info>php %command.full_name% path/to/dir path/to/another/dir</info>

To restrict the updates to one or more locales, including the default locale itself, use the <comment>--locales</comment> option:

  <info>php %command.full_name% --locales en --locales fr</info>

You can specify one or more domains to target with the <comment>--domains</comment> option. By default, all available domains for the targeted locales are used.

  <info>php %command.full_name% --domains messages</info>

EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $format = $input->getOption('format');

        if (!\array_key_exists($format, self::FORMATS)) {
            $io->error(sprintf('Unknown format "%s". Available formats are: %s.', $format, implode(', ', array_map(fn ($f) => '"'.$f.'"', array_keys(self::FORMATS)))));

            return self::INVALID;
        }

        [$format, $xliffVersion] = self::FORMATS[$format];

        $locales = $input->getOption('locales');

        if (!$locales) {
            $io->error('No locales provided in --locales options and no defaults provided to the command.');

            return self::INVALID;
        }

        $transPaths = $input->getArgument('paths') ?: $this->transPaths;

        if (!$transPaths) {
            $io->error('No paths specified in arguments, and no default paths provided to the command.');

            return self::INVALID;
        }

        $domains = $input->getOption('domains');

        $io->title('XLIFF Source Tag Updater');

        foreach ($transPaths as $transPath) {
            $io->comment(sprintf('Updating XLIFF files in <info>%s</info>...', $transPath));

            $translatorBag = $this->readLocalTranslations(array_unique(array_merge($locales, [$this->defaultLocale])), $domains, [$transPath]);

            $defaultLocaleCatalogue = $translatorBag->getCatalogue($this->defaultLocale);

            if (!$defaultLocaleCatalogue instanceof MetadataAwareInterface) {
                $io->error(sprintf('The default locale catalogue must implement "%s" to be used in this tool.', MetadataAwareInterface::class));

                return self::FAILURE;
            }

            foreach ($locales as $locale) {
                $currentCatalogue = $translatorBag->getCatalogue($locale);

                if (!$currentCatalogue instanceof MessageCatalogue) {
                    $io->warning(sprintf('The catalogue for locale "%s" must be an instance of "%s" to be used in this tool.', $locale, MessageCatalogue::class));

                    continue;
                }

                if (!\count($currentCatalogue->getDomains())) {
                    $io->warning(sprintf('No messages found for locale "%s".', $locale));

                    continue;
                }

                $updateSourceCount = 0;

                foreach ($currentCatalogue->getDomains() as $domain) {
                    // Update source metadata with default locale target for each message in result catalogue
                    foreach ($currentCatalogue->all($domain) as $key => $value) {
                        if (!$defaultLocaleCatalogue->has($key, $domain)) {
                            continue;
                        }

                        $resultMetadata = $currentCatalogue->getMetadata($key, $domain);
                        $defaultTranslation = $defaultLocaleCatalogue->get($key, $domain);
                        if (!isset($resultMetadata['source']) || $resultMetadata['source'] !== $defaultTranslation) {
                            ++$updateSourceCount;
                            $resultMetadata['source'] = $defaultTranslation;
                            $currentCatalogue->setMetadata($key, $resultMetadata, $domain);
                        }
                    }
                }

                $this->writer->write($currentCatalogue, $format, ['path' => $transPath, 'default_locale' => $this->defaultLocale, 'xliff_version' => $xliffVersion]);

                if (0 === $updateSourceCount) {
                    $message = sprintf('All source tags are already up-to-date for locale "%s".', $locale);
                } else {
                    $message = sprintf('Updated %d source tag%s for locale "%s".', $updateSourceCount, $updateSourceCount > 1 ? 's' : '', $locale);
                }

                $io->info($message);
            }
        }

        $io->success('Operation succeeded.');

        return self::SUCCESS;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestOptionValuesFor('locales')) {
            $suggestions->suggestValues($this->enabledLocales);

            return;
        }

        if ($input->mustSuggestOptionValuesFor('format')) {
            $suggestions->suggestValues(array_keys(self::FORMATS));

            return;
        }

        if ($input->mustSuggestOptionValuesFor('domains') && $locales = $input->getOption('locales')) {
            $suggestedDomains = [];
            $translatorBag = $this->readLocalTranslations($locales, [], $input->getArgument('paths') ?: $this->transPaths);
            foreach ($translatorBag->getCatalogues() as $catalogue) {
                array_push($suggestedDomains, ...$catalogue->getDomains());
            }
            if ($suggestedDomains) {
                $suggestions->suggestValues(array_unique($suggestedDomains));
            }
        }
    }
}
