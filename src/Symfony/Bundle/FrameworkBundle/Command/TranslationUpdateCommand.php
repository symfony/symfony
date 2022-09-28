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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Translation\Catalogue\MergeOperation;
use Symfony\Component\Translation\Catalogue\TargetOperation;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\Reader\TranslationReaderInterface;
use Symfony\Component\Translation\Writer\TranslationWriterInterface;

/**
 * A command that parses templates to extract translation messages and adds them
 * into the translation files.
 *
 * @author Michel Salib <michelsalib@hotmail.com>
 *
 * @final
 */
class TranslationUpdateCommand extends Command
{
    private const ASC = 'asc';
    private const DESC = 'desc';
    private const SORT_ORDERS = [self::ASC, self::DESC];
    private const FORMATS = [
        'xlf12' => ['xlf', '1.2'],
        'xlf20' => ['xlf', '2.0'],
    ];

    protected static $defaultName = 'translation:extract|translation:update';
    protected static $defaultDescription = 'Extract missing translations keys from code to translation files.';

    private $writer;
    private $reader;
    private $extractor;
    private $defaultLocale;
    private $defaultTransPath;
    private $defaultViewsPath;
    private $transPaths;
    private $codePaths;
    private $enabledLocales;

    public function __construct(TranslationWriterInterface $writer, TranslationReaderInterface $reader, ExtractorInterface $extractor, string $defaultLocale, string $defaultTransPath = null, string $defaultViewsPath = null, array $transPaths = [], array $codePaths = [], array $enabledLocales = [])
    {
        parent::__construct();

        $this->writer = $writer;
        $this->reader = $reader;
        $this->extractor = $extractor;
        $this->defaultLocale = $defaultLocale;
        $this->defaultTransPath = $defaultTransPath;
        $this->defaultViewsPath = $defaultViewsPath;
        $this->transPaths = $transPaths;
        $this->codePaths = $codePaths;
        $this->enabledLocales = $enabledLocales;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition([
                new InputArgument('locale', InputArgument::REQUIRED, 'The locale'),
                new InputArgument('bundle', InputArgument::OPTIONAL, 'The bundle name or directory where to load the messages'),
                new InputOption('prefix', null, InputOption::VALUE_OPTIONAL, 'Override the default prefix', '__'),
                new InputOption('output-format', null, InputOption::VALUE_OPTIONAL, 'Override the default output format (deprecated)'),
                new InputOption('format', null, InputOption::VALUE_OPTIONAL, 'Override the default output format', 'xlf12'),
                new InputOption('dump-messages', null, InputOption::VALUE_NONE, 'Should the messages be dumped in the console'),
                new InputOption('force', null, InputOption::VALUE_NONE, 'Should the extract be done'),
                new InputOption('clean', null, InputOption::VALUE_NONE, 'Should clean not found messages'),
                new InputOption('domain', null, InputOption::VALUE_OPTIONAL, 'Specify the domain to extract'),
                new InputOption('xliff-version', null, InputOption::VALUE_OPTIONAL, 'Override the default xliff version (deprecated)'),
                new InputOption('sort', null, InputOption::VALUE_OPTIONAL, 'Return list of messages sorted alphabetically', 'asc'),
                new InputOption('as-tree', null, InputOption::VALUE_OPTIONAL, 'Dump the messages as a tree-like structure: The given value defines the level where to switch to inline YAML'),
            ])
            ->setDescription(self::$defaultDescription)
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command extracts translation strings from templates
of a given bundle or the default translations directory. It can display them or merge
the new ones into the translation files.

When new translation strings are found it can automatically add a prefix to the translation
message.

Example running against a Bundle (AcmeBundle)

  <info>php %command.full_name% --dump-messages en AcmeBundle</info>
  <info>php %command.full_name% --force --prefix="new_" fr AcmeBundle</info>

Example running against default messages directory

  <info>php %command.full_name% --dump-messages en</info>
  <info>php %command.full_name% --force --prefix="new_" fr</info>

You can sort the output with the <comment>--sort</> flag:

    <info>php %command.full_name% --dump-messages --sort=asc en AcmeBundle</info>
    <info>php %command.full_name% --dump-messages --sort=desc fr</info>

You can dump a tree-like structure using the yaml format with <comment>--as-tree</> flag:

    <info>php %command.full_name% --force --format=yaml --as-tree=3 en AcmeBundle</info>
    <info>php %command.full_name% --force --format=yaml --sort=asc --as-tree=3 fr</info>

EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $errorIo = $output instanceof ConsoleOutputInterface ? new SymfonyStyle($input, $output->getErrorOutput()) : $io;

        if ('translation:update' === $input->getFirstArgument()) {
            $errorIo->caution('Command "translation:update" is deprecated since version 5.4 and will be removed in Symfony 6.0. Use "translation:extract" instead.');
        }

        $io = new SymfonyStyle($input, $output);
        $errorIo = $io->getErrorStyle();

        // check presence of force or dump-message
        if (true !== $input->getOption('force') && true !== $input->getOption('dump-messages')) {
            $errorIo->error('You must choose one of --force or --dump-messages');

            return 1;
        }

        $format = $input->getOption('output-format') ?: $input->getOption('format');
        $xliffVersion = $input->getOption('xliff-version') ?? '1.2';

        if ($input->getOption('xliff-version')) {
            $errorIo->warning(sprintf('The "--xliff-version" option is deprecated since version 5.3, use "--format=xlf%d" instead.', 10 * $xliffVersion));
        }

        if ($input->getOption('output-format')) {
            $errorIo->warning(sprintf('The "--output-format" option is deprecated since version 5.3, use "--format=xlf%d" instead.', 10 * $xliffVersion));
        }

        if (\in_array($format, array_keys(self::FORMATS), true)) {
            [$format, $xliffVersion] = self::FORMATS[$format];
        }

        // check format
        $supportedFormats = $this->writer->getFormats();
        if (!\in_array($format, $supportedFormats, true)) {
            $errorIo->error(['Wrong output format', 'Supported formats are: '.implode(', ', $supportedFormats).', xlf12 and xlf20.']);

            return 1;
        }

        /** @var KernelInterface $kernel */
        $kernel = $this->getApplication()->getKernel();

        // Define Root Paths
        $transPaths = $this->getRootTransPaths();
        $codePaths = $this->getRootCodePaths($kernel);

        $currentName = 'default directory';

        // Override with provided Bundle info
        if (null !== $input->getArgument('bundle')) {
            try {
                $foundBundle = $kernel->getBundle($input->getArgument('bundle'));
                $bundleDir = $foundBundle->getPath();
                $transPaths = [is_dir($bundleDir.'/Resources/translations') ? $bundleDir.'/Resources/translations' : $bundleDir.'/translations'];
                $codePaths = [is_dir($bundleDir.'/Resources/views') ? $bundleDir.'/Resources/views' : $bundleDir.'/templates'];
                if ($this->defaultTransPath) {
                    $transPaths[] = $this->defaultTransPath;
                }
                if ($this->defaultViewsPath) {
                    $codePaths[] = $this->defaultViewsPath;
                }
                $currentName = $foundBundle->getName();
            } catch (\InvalidArgumentException $e) {
                // such a bundle does not exist, so treat the argument as path
                $path = $input->getArgument('bundle');

                $transPaths = [$path.'/translations'];
                $codePaths = [$path.'/templates'];

                if (!is_dir($transPaths[0])) {
                    throw new InvalidArgumentException(sprintf('"%s" is neither an enabled bundle nor a directory.', $transPaths[0]));
                }
            }
        }

        $io->title('Translation Messages Extractor and Dumper');
        $io->comment(sprintf('Generating "<info>%s</info>" translation files for "<info>%s</info>"', $input->getArgument('locale'), $currentName));

        $io->comment('Parsing templates...');
        $extractedCatalogue = $this->extractMessages($input->getArgument('locale'), $codePaths, $input->getOption('prefix'));

        $io->comment('Loading translation files...');
        $currentCatalogue = $this->loadCurrentMessages($input->getArgument('locale'), $transPaths);

        if (null !== $domain = $input->getOption('domain')) {
            $currentCatalogue = $this->filterCatalogue($currentCatalogue, $domain);
            $extractedCatalogue = $this->filterCatalogue($extractedCatalogue, $domain);
        }

        // process catalogues
        $operation = $input->getOption('clean')
            ? new TargetOperation($currentCatalogue, $extractedCatalogue)
            : new MergeOperation($currentCatalogue, $extractedCatalogue);

        // Exit if no messages found.
        if (!\count($operation->getDomains())) {
            $errorIo->warning('No translation messages were found.');

            return 0;
        }

        $resultMessage = 'Translation files were successfully updated';

        $operation->moveMessagesToIntlDomainsIfPossible('new');

        // show compiled list of messages
        if (true === $input->getOption('dump-messages')) {
            $extractedMessagesCount = 0;
            $io->newLine();
            foreach ($operation->getDomains() as $domain) {
                $newKeys = array_keys($operation->getNewMessages($domain));
                $allKeys = array_keys($operation->getMessages($domain));

                $list = array_merge(
                    array_diff($allKeys, $newKeys),
                    array_map(function ($id) {
                        return sprintf('<fg=green>%s</>', $id);
                    }, $newKeys),
                    array_map(function ($id) {
                        return sprintf('<fg=red>%s</>', $id);
                    }, array_keys($operation->getObsoleteMessages($domain)))
                );

                $domainMessagesCount = \count($list);

                if ($sort = $input->getOption('sort')) {
                    $sort = strtolower($sort);
                    if (!\in_array($sort, self::SORT_ORDERS, true)) {
                        $errorIo->error(['Wrong sort order', 'Supported formats are: '.implode(', ', self::SORT_ORDERS).'.']);

                        return 1;
                    }

                    if (self::DESC === $sort) {
                        rsort($list);
                    } else {
                        sort($list);
                    }
                }

                $io->section(sprintf('Messages extracted for domain "<info>%s</info>" (%d message%s)', $domain, $domainMessagesCount, $domainMessagesCount > 1 ? 's' : ''));
                $io->listing($list);

                $extractedMessagesCount += $domainMessagesCount;
            }

            if ('xlf' === $format) {
                $io->comment(sprintf('Xliff output version is <info>%s</info>', $xliffVersion));
            }

            $resultMessage = sprintf('%d message%s successfully extracted', $extractedMessagesCount, $extractedMessagesCount > 1 ? 's were' : ' was');
        }

        // save the files
        if (true === $input->getOption('force')) {
            $io->comment('Writing files...');

            $bundleTransPath = false;
            foreach ($transPaths as $path) {
                if (is_dir($path)) {
                    $bundleTransPath = $path;
                }
            }

            if (!$bundleTransPath) {
                $bundleTransPath = end($transPaths);
            }

            $this->writer->write($operation->getResult(), $format, ['path' => $bundleTransPath, 'default_locale' => $this->defaultLocale, 'xliff_version' => $xliffVersion, 'as_tree' => $input->getOption('as-tree'), 'inline' => $input->getOption('as-tree') ?? 0]);

            if (true === $input->getOption('dump-messages')) {
                $resultMessage .= ' and translation files were updated';
            }
        }

        $io->success($resultMessage.'.');

        return 0;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('locale')) {
            $suggestions->suggestValues($this->enabledLocales);

            return;
        }

        /** @var KernelInterface $kernel */
        $kernel = $this->getApplication()->getKernel();
        if ($input->mustSuggestArgumentValuesFor('bundle')) {
            $bundles = [];

            foreach ($kernel->getBundles() as $bundle) {
                $bundles[] = $bundle->getName();
                if ($bundle->getContainerExtension()) {
                    $bundles[] = $bundle->getContainerExtension()->getAlias();
                }
            }

            $suggestions->suggestValues($bundles);

            return;
        }

        if ($input->mustSuggestOptionValuesFor('format')) {
            $suggestions->suggestValues(array_merge(
                $this->writer->getFormats(),
                array_keys(self::FORMATS)
            ));

            return;
        }

        if ($input->mustSuggestOptionValuesFor('domain') && $locale = $input->getArgument('locale')) {
            $extractedCatalogue = $this->extractMessages($locale, $this->getRootCodePaths($kernel), $input->getOption('prefix'));

            $currentCatalogue = $this->loadCurrentMessages($locale, $this->getRootTransPaths());

            // process catalogues
            $operation = $input->getOption('clean')
                ? new TargetOperation($currentCatalogue, $extractedCatalogue)
                : new MergeOperation($currentCatalogue, $extractedCatalogue);

            $suggestions->suggestValues($operation->getDomains());

            return;
        }

        if ($input->mustSuggestOptionValuesFor('sort')) {
            $suggestions->suggestValues(self::SORT_ORDERS);
        }
    }

    private function filterCatalogue(MessageCatalogue $catalogue, string $domain): MessageCatalogue
    {
        $filteredCatalogue = new MessageCatalogue($catalogue->getLocale());

        // extract intl-icu messages only
        $intlDomain = $domain.MessageCatalogueInterface::INTL_DOMAIN_SUFFIX;
        if ($intlMessages = $catalogue->all($intlDomain)) {
            $filteredCatalogue->add($intlMessages, $intlDomain);
        }

        // extract all messages and subtract intl-icu messages
        if ($messages = array_diff($catalogue->all($domain), $intlMessages)) {
            $filteredCatalogue->add($messages, $domain);
        }
        foreach ($catalogue->getResources() as $resource) {
            $filteredCatalogue->addResource($resource);
        }

        if ($metadata = $catalogue->getMetadata('', $intlDomain)) {
            foreach ($metadata as $k => $v) {
                $filteredCatalogue->setMetadata($k, $v, $intlDomain);
            }
        }

        if ($metadata = $catalogue->getMetadata('', $domain)) {
            foreach ($metadata as $k => $v) {
                $filteredCatalogue->setMetadata($k, $v, $domain);
            }
        }

        return $filteredCatalogue;
    }

    private function extractMessages(string $locale, array $transPaths, string $prefix): MessageCatalogue
    {
        $extractedCatalogue = new MessageCatalogue($locale);
        $this->extractor->setPrefix($prefix);
        $transPaths = $this->filterDuplicateTransPaths($transPaths);
        foreach ($transPaths as $path) {
            if (is_dir($path) || is_file($path)) {
                $this->extractor->extract($path, $extractedCatalogue);
            }
        }

        return $extractedCatalogue;
    }

    private function filterDuplicateTransPaths(array $transPaths): array
    {
        $transPaths = array_filter(array_map('realpath', $transPaths));

        sort($transPaths);

        $filteredPaths = [];

        foreach ($transPaths as $path) {
            foreach ($filteredPaths as $filteredPath) {
                if (str_starts_with($path, $filteredPath.\DIRECTORY_SEPARATOR)) {
                    continue 2;
                }
            }

            $filteredPaths[] = $path;
        }

        return $filteredPaths;
    }

    private function loadCurrentMessages(string $locale, array $transPaths): MessageCatalogue
    {
        $currentCatalogue = new MessageCatalogue($locale);
        foreach ($transPaths as $path) {
            if (is_dir($path)) {
                $this->reader->read($path, $currentCatalogue);
            }
        }

        return $currentCatalogue;
    }

    private function getRootTransPaths(): array
    {
        $transPaths = $this->transPaths;
        if ($this->defaultTransPath) {
            $transPaths[] = $this->defaultTransPath;
        }

        return $transPaths;
    }

    private function getRootCodePaths(KernelInterface $kernel): array
    {
        $codePaths = $this->codePaths;
        $codePaths[] = $kernel->getProjectDir().'/src';
        if ($this->defaultViewsPath) {
            $codePaths[] = $this->defaultViewsPath;
        }

        return $codePaths;
    }
}
