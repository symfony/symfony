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
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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

    protected static $defaultName = 'translation:update';

    private $writer;
    private $reader;
    private $extractor;
    private $defaultLocale;
    private $defaultTransPath;
    private $defaultViewsPath;
    private $transPaths;
    private $viewsPaths;

    public function __construct(TranslationWriterInterface $writer, TranslationReaderInterface $reader, ExtractorInterface $extractor, string $defaultLocale, string $defaultTransPath = null, string $defaultViewsPath = null, array $transPaths = [], array $viewsPaths = [])
    {
        parent::__construct();

        $this->writer = $writer;
        $this->reader = $reader;
        $this->extractor = $extractor;
        $this->defaultLocale = $defaultLocale;
        $this->defaultTransPath = $defaultTransPath;
        $this->defaultViewsPath = $defaultViewsPath;
        $this->transPaths = $transPaths;
        $this->viewsPaths = $viewsPaths;
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
                new InputOption('output-format', null, InputOption::VALUE_OPTIONAL, 'Override the default output format', 'xlf'),
                new InputOption('dump-messages', null, InputOption::VALUE_NONE, 'Should the messages be dumped in the console'),
                new InputOption('force', null, InputOption::VALUE_NONE, 'Should the update be done'),
                new InputOption('no-backup', null, InputOption::VALUE_NONE, 'Should backup be disabled'),
                new InputOption('clean', null, InputOption::VALUE_NONE, 'Should clean not found messages'),
                new InputOption('domain', null, InputOption::VALUE_OPTIONAL, 'Specify the domain to update'),
                new InputOption('xliff-version', null, InputOption::VALUE_OPTIONAL, 'Override the default xliff version', '1.2'),
                new InputOption('sort', null, InputOption::VALUE_OPTIONAL, 'Return list of messages sorted alphabetically', 'asc'),
            ])
            ->setDescription('Updates the translation file')
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
        $errorIo = $io->getErrorStyle();

        // check presence of force or dump-message
        if (true !== $input->getOption('force') && true !== $input->getOption('dump-messages')) {
            $errorIo->error('You must choose one of --force or --dump-messages');

            return 1;
        }

        // check format
        $supportedFormats = $this->writer->getFormats();
        if (!\in_array($input->getOption('output-format'), $supportedFormats, true)) {
            $errorIo->error(['Wrong output format', 'Supported formats are: '.implode(', ', $supportedFormats).'.']);

            return 1;
        }
        /** @var KernelInterface $kernel */
        $kernel = $this->getApplication()->getKernel();
        $rootDir = $kernel->getContainer()->getParameter('kernel.root_dir');

        // Define Root Paths
        $transPaths = $this->transPaths;
        if (is_dir($dir = $rootDir.'/Resources/translations')) {
            if ($dir !== $this->defaultTransPath) {
                $notice = sprintf('Storing translations in the "%s" directory is deprecated since Symfony 4.2, ', $dir);
                @trigger_error($notice.($this->defaultTransPath ? sprintf('use the "%s" directory instead.', $this->defaultTransPath) : 'configure and use "framework.translator.default_path" instead.'), \E_USER_DEPRECATED);
            }
            $transPaths[] = $dir;
        }
        if ($this->defaultTransPath) {
            $transPaths[] = $this->defaultTransPath;
        }
        $viewsPaths = $this->viewsPaths;
        if (is_dir($dir = $rootDir.'/Resources/views')) {
            if ($dir !== $this->defaultViewsPath) {
                $notice = sprintf('Storing templates in the "%s" directory is deprecated since Symfony 4.2, ', $dir);
                @trigger_error($notice.($this->defaultViewsPath ? sprintf('use the "%s" directory instead.', $this->defaultViewsPath) : 'configure and use "twig.default_path" instead.'), \E_USER_DEPRECATED);
            }
            $viewsPaths[] = $dir;
        }
        if ($this->defaultViewsPath) {
            $viewsPaths[] = $this->defaultViewsPath;
        }
        $currentName = 'default directory';

        // Override with provided Bundle info
        if (null !== $input->getArgument('bundle')) {
            try {
                $foundBundle = $kernel->getBundle($input->getArgument('bundle'));
                $bundleDir = $foundBundle->getPath();
                $transPaths = [is_dir($bundleDir.'/Resources/translations') ? $bundleDir.'/Resources/translations' : $bundleDir.'/translations'];
                $viewsPaths = [is_dir($bundleDir.'/Resources/views') ? $bundleDir.'/Resources/views' : $bundleDir.'/templates'];
                if ($this->defaultTransPath) {
                    $transPaths[] = $this->defaultTransPath;
                }
                if (is_dir($dir = sprintf('%s/Resources/%s/translations', $rootDir, $foundBundle->getName()))) {
                    $transPaths[] = $dir;
                    $notice = sprintf('Storing translations files for "%s" in the "%s" directory is deprecated since Symfony 4.2, ', $foundBundle->getName(), $dir);
                    @trigger_error($notice.($this->defaultTransPath ? sprintf('use the "%s" directory instead.', $this->defaultTransPath) : 'configure and use "framework.translator.default_path" instead.'), \E_USER_DEPRECATED);
                }
                if ($this->defaultViewsPath) {
                    $viewsPaths[] = $this->defaultViewsPath;
                }
                if (is_dir($dir = sprintf('%s/Resources/%s/views', $rootDir, $foundBundle->getName()))) {
                    $viewsPaths[] = $dir;
                    $notice = sprintf('Storing templates for "%s" in the "%s" directory is deprecated since Symfony 4.2, ', $foundBundle->getName(), $dir);
                    @trigger_error($notice.($this->defaultViewsPath ? sprintf('use the "%s" directory instead.', $this->defaultViewsPath) : 'configure and use "twig.default_path" instead.'), \E_USER_DEPRECATED);
                }
                $currentName = $foundBundle->getName();
            } catch (\InvalidArgumentException $e) {
                // such a bundle does not exist, so treat the argument as path
                $path = $input->getArgument('bundle');

                $transPaths = [$path.'/translations'];
                if (is_dir($dir = $path.'/Resources/translations')) {
                    if ($dir !== $this->defaultTransPath) {
                        @trigger_error(sprintf('Storing translations in the "%s" directory is deprecated since Symfony 4.2, use the "%s" directory instead.', $dir, $path.'/translations'), \E_USER_DEPRECATED);
                    }
                    $transPaths[] = $dir;
                }

                $viewsPaths = [$path.'/templates'];
                if (is_dir($dir = $path.'/Resources/views')) {
                    if ($dir !== $this->defaultViewsPath) {
                        @trigger_error(sprintf('Storing templates in the "%s" directory is deprecated since Symfony 4.2, use the "%s" directory instead.', $dir, $path.'/templates'), \E_USER_DEPRECATED);
                    }
                    $viewsPaths[] = $dir;
                }

                if (!is_dir($transPaths[0]) && !isset($transPaths[1])) {
                    throw new InvalidArgumentException(sprintf('"%s" is neither an enabled bundle nor a directory.', $transPaths[0]));
                }
            }
        }

        $io->title('Translation Messages Extractor and Dumper');
        $io->comment(sprintf('Generating "<info>%s</info>" translation files for "<info>%s</info>"', $input->getArgument('locale'), $currentName));

        // load any messages from templates
        $extractedCatalogue = new MessageCatalogue($input->getArgument('locale'));
        $io->comment('Parsing templates...');
        $this->extractor->setPrefix($input->getOption('prefix'));
        foreach ($viewsPaths as $path) {
            if (is_dir($path) || is_file($path)) {
                $this->extractor->extract($path, $extractedCatalogue);
            }
        }

        // load any existing messages from the translation files
        $currentCatalogue = new MessageCatalogue($input->getArgument('locale'));
        $io->comment('Loading translation files...');
        foreach ($transPaths as $path) {
            if (is_dir($path)) {
                $this->reader->read($path, $currentCatalogue);
            }
        }

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

        // move new messages to intl domain when possible
        if (class_exists(\MessageFormatter::class)) {
            foreach ($operation->getDomains() as $domain) {
                $intlDomain = $domain.MessageCatalogueInterface::INTL_DOMAIN_SUFFIX;
                $newMessages = $operation->getNewMessages($domain);

                if ([] === $newMessages || ([] === $currentCatalogue->all($intlDomain) && [] !== $currentCatalogue->all($domain))) {
                    continue;
                }

                $result = $operation->getResult();
                $allIntlMessages = $result->all($intlDomain);
                $currentMessages = array_diff_key($newMessages, $result->all($domain));
                $result->replace($currentMessages, $domain);
                $result->replace($allIntlMessages + $newMessages, $intlDomain);
            }
        }

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

            if ('xlf' === $input->getOption('output-format')) {
                $io->comment(sprintf('Xliff output version is <info>%s</info>', $input->getOption('xliff-version')));
            }

            $resultMessage = sprintf('%d message%s successfully extracted', $extractedMessagesCount, $extractedMessagesCount > 1 ? 's were' : ' was');
        }

        if (true === $input->getOption('no-backup')) {
            $this->writer->disableBackup();
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

            $this->writer->write($operation->getResult(), $input->getOption('output-format'), ['path' => $bundleTransPath, 'default_locale' => $this->defaultLocale, 'xliff_version' => $input->getOption('xliff-version')]);

            if (true === $input->getOption('dump-messages')) {
                $resultMessage .= ' and translation files were updated';
            }
        }

        $io->success($resultMessage.'.');

        return 0;
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
        if ($metadata = $catalogue->getMetadata('', $domain)) {
            foreach ($metadata as $k => $v) {
                $filteredCatalogue->setMetadata($k, $v, $domain);
            }
        }

        return $filteredCatalogue;
    }
}
