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

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Translation\Catalogue\TargetOperation;
use Symfony\Component\Translation\Catalogue\MergeOperation;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Reader\TranslationReaderInterface;
use Symfony\Component\Translation\Writer\TranslationWriterInterface;

/**
 * A command that parses templates to extract translation messages and adds them
 * into the translation files.
 *
 * @author Michel Salib <michelsalib@hotmail.com>
 *
 * @final since version 3.4
 */
class TranslationUpdateCommand extends ContainerAwareCommand
{
    protected static $defaultName = 'translation:update';

    private $writer;
    private $reader;
    private $extractor;
    private $defaultLocale;
    private $defaultTransPath;
    private $defaultViewsPath;

    /**
     * @param TranslationWriterInterface $writer
     * @param TranslationReaderInterface $reader
     * @param ExtractorInterface         $extractor
     * @param string                     $defaultLocale
     * @param string                     $defaultTransPath
     * @param string                     $defaultViewsPath
     */
    public function __construct($writer = null, TranslationReaderInterface $reader = null, ExtractorInterface $extractor = null, $defaultLocale = null, $defaultTransPath = null, $defaultViewsPath = null)
    {
        if (!$writer instanceof TranslationWriterInterface) {
            @trigger_error(sprintf('%s() expects an instance of "%s" as first argument since version 3.4. Not passing it is deprecated and will throw a TypeError in 4.0.', __METHOD__, TranslationWriterInterface::class), E_USER_DEPRECATED);

            parent::__construct($writer);

            return;
        }

        parent::__construct();

        $this->writer = $writer;
        $this->reader = $reader;
        $this->extractor = $extractor;
        $this->defaultLocale = $defaultLocale;
        $this->defaultTransPath = $defaultTransPath;
        $this->defaultViewsPath = $defaultViewsPath;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('locale', InputArgument::REQUIRED, 'The locale'),
                new InputArgument('bundle', InputArgument::OPTIONAL, 'The bundle name or directory where to load the messages, defaults to app/Resources folder'),
                new InputOption('prefix', null, InputOption::VALUE_OPTIONAL, 'Override the default prefix', '__'),
                new InputOption('no-prefix', null, InputOption::VALUE_NONE, '[DEPRECATED] If set, no prefix is added to the translations'),
                new InputOption('output-format', null, InputOption::VALUE_OPTIONAL, 'Override the default output format', 'yml'),
                new InputOption('dump-messages', null, InputOption::VALUE_NONE, 'Should the messages be dumped in the console'),
                new InputOption('force', null, InputOption::VALUE_NONE, 'Should the update be done'),
                new InputOption('no-backup', null, InputOption::VALUE_NONE, 'Should backup be disabled'),
                new InputOption('clean', null, InputOption::VALUE_NONE, 'Should clean not found messages'),
                new InputOption('domain', null, InputOption::VALUE_OPTIONAL, 'Specify the domain to update'),
            ))
            ->setDescription('Updates the translation file')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command extracts translation strings from templates
of a given bundle or the app folder. It can display them or merge the new ones into the translation files.

When new translation strings are found it can automatically add a prefix to the translation
message.

Example running against a Bundle (AcmeBundle)
  <info>php %command.full_name% --dump-messages en AcmeBundle</info>
  <info>php %command.full_name% --force --prefix="new_" fr AcmeBundle</info>

Example running against app messages (app/Resources folder)
  <info>php %command.full_name% --dump-messages en</info>
  <info>php %command.full_name% --force --prefix="new_" fr</info>
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     *
     * BC to be removed in 4.0
     */
    public function isEnabled()
    {
        if (null !== $this->writer) {
            return parent::isEnabled();
        }
        if (!class_exists('Symfony\Component\Translation\Translator')) {
            return false;
        }

        return parent::isEnabled();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // BC to be removed in 4.0
        if (null === $this->writer) {
            $this->writer = $this->getContainer()->get('translation.writer');
            $this->reader = $this->getContainer()->get('translation.reader');
            $this->extractor = $this->getContainer()->get('translation.extractor');
            $this->defaultLocale = $this->getContainer()->getParameter('kernel.default_locale');
        }

        $io = new SymfonyStyle($input, $output);
        $errorIo = $io->getErrorStyle();

        // check presence of force or dump-message
        if (true !== $input->getOption('force') && true !== $input->getOption('dump-messages')) {
            $errorIo->error('You must choose one of --force or --dump-messages');

            return 1;
        }

        // check format
        $supportedFormats = $this->writer->getFormats();
        if (!in_array($input->getOption('output-format'), $supportedFormats)) {
            $errorIo->error(array('Wrong output format', 'Supported formats are: '.implode(', ', $supportedFormats).'.'));

            return 1;
        }
        /** @var KernelInterface $kernel */
        $kernel = $this->getApplication()->getKernel();

        // Define Root Paths
        $transPaths = array($kernel->getRootDir().'/Resources/translations');
        if ($this->defaultTransPath) {
            $transPaths[] = $this->defaultTransPath;
        }
        $viewsPaths = array($kernel->getRootDir().'/Resources/views');
        if ($this->defaultViewsPath) {
            $viewsPaths[] = $this->defaultViewsPath;
        }
        $currentName = 'app folder';

        // Override with provided Bundle info
        if (null !== $input->getArgument('bundle')) {
            try {
                $foundBundle = $kernel->getBundle($input->getArgument('bundle'));
                $transPaths = array($foundBundle->getPath().'/Resources/translations');
                if ($this->defaultTransPath) {
                    $transPaths[] = $this->defaultTransPath.'/'.$foundBundle->getName();
                }
                $transPaths[] = sprintf('%s/Resources/%s/translations', $kernel->getRootDir(), $foundBundle->getName());
                $viewsPaths = array($foundBundle->getPath().'/Resources/views');
                if ($this->defaultViewsPath) {
                    $viewsPaths[] = $this->defaultViewsPath.'/bundles/'.$foundBundle->getName();
                }
                $viewsPaths[] = sprintf('%s/Resources/%s/views', $kernel->getRootDir(), $foundBundle->getName());
                $currentName = $foundBundle->getName();
            } catch (\InvalidArgumentException $e) {
                // such a bundle does not exist, so treat the argument as path
                $transPaths = array($input->getArgument('bundle').'/Resources/translations');
                $viewsPaths = array($input->getArgument('bundle').'/Resources/views');
                $currentName = $transPaths[0];

                if (!is_dir($transPaths[0])) {
                    throw new \InvalidArgumentException(sprintf('<error>"%s" is neither an enabled bundle nor a directory.</error>', $transPaths[0]));
                }
            }
        }

        $errorIo->title('Translation Messages Extractor and Dumper');
        $errorIo->comment(sprintf('Generating "<info>%s</info>" translation files for "<info>%s</info>"', $input->getArgument('locale'), $currentName));

        // load any messages from templates
        $extractedCatalogue = new MessageCatalogue($input->getArgument('locale'));
        $errorIo->comment('Parsing templates...');
        $prefix = $input->getOption('prefix');
        // @deprecated since version 3.4, to be removed in 4.0 along with the --no-prefix option
        if ($input->getOption('no-prefix')) {
            @trigger_error('The "--no-prefix" option is deprecated since version 3.4 and will be removed in 4.0. Use the "--prefix" option with an empty string as value instead.', E_USER_DEPRECATED);
            $prefix = '';
        }
        $this->extractor->setPrefix($prefix);
        foreach ($viewsPaths as $path) {
            if (is_dir($path)) {
                $this->extractor->extract($path, $extractedCatalogue);
            }
        }

        // load any existing messages from the translation files
        $currentCatalogue = new MessageCatalogue($input->getArgument('locale'));
        $errorIo->comment('Loading translation files...');
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
        if (!count($operation->getDomains())) {
            $errorIo->warning('No translation messages were found.');

            return;
        }

        $resultMessage = 'Translation files were successfully updated';

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

                $domainMessagesCount = count($list);

                $io->section(sprintf('Messages extracted for domain "<info>%s</info>" (%d message%s)', $domain, $domainMessagesCount, $domainMessagesCount > 1 ? 's' : ''));
                $io->listing($list);

                $extractedMessagesCount += $domainMessagesCount;
            }

            if ('xlf' == $input->getOption('output-format')) {
                $errorIo->comment('Xliff output version is <info>1.2</info>');
            }

            $resultMessage = sprintf('%d message%s successfully extracted', $extractedMessagesCount, $extractedMessagesCount > 1 ? 's were' : ' was');
        }

        if (true === $input->getOption('no-backup')) {
            $this->writer->disableBackup();
        }

        // save the files
        if (true === $input->getOption('force')) {
            $errorIo->comment('Writing files...');

            $bundleTransPath = false;
            foreach ($transPaths as $path) {
                if (is_dir($path)) {
                    $bundleTransPath = $path;
                }
            }

            if (!$bundleTransPath) {
                $bundleTransPath = end($transPaths);
            }

            $this->writer->write($operation->getResult(), $input->getOption('output-format'), array('path' => $bundleTransPath, 'default_locale' => $this->defaultLocale));

            if (true === $input->getOption('dump-messages')) {
                $resultMessage .= ' and translation files were updated';
            }
        }

        $errorIo->success($resultMessage.'.');
    }

    private function filterCatalogue(MessageCatalogue $catalogue, $domain)
    {
        $filteredCatalogue = new MessageCatalogue($catalogue->getLocale());

        if ($messages = $catalogue->all($domain)) {
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
