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
use Symfony\Component\Translation\Catalogue\DiffOperation;
use Symfony\Component\Translation\Catalogue\MergeOperation;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * A command that parse templates to extract translation messages and add them into the translation files.
 *
 * @author Michel Salib <michelsalib@hotmail.com>
 */
class TranslationUpdateCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('translation:update')
            ->setDefinition(array(
                new InputArgument('locale', InputArgument::REQUIRED, 'The locale'),
                new InputArgument('bundle', InputArgument::OPTIONAL, 'The bundle name or directory where to load the messages, defaults to app/Resources folder'),
                new InputOption('prefix', null, InputOption::VALUE_OPTIONAL, 'Override the default prefix', '__'),
                new InputOption('output-format', null, InputOption::VALUE_OPTIONAL, 'Override the default output format', 'yml'),
                new InputOption('dump-messages', null, InputOption::VALUE_NONE, 'Should the messages be dumped in the console'),
                new InputOption('force', null, InputOption::VALUE_NONE, 'Should the update be done'),
                new InputOption('no-backup', null, InputOption::VALUE_NONE, 'Should backup be disabled'),
                new InputOption('clean', null, InputOption::VALUE_NONE, 'Should clean not found messages'),
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
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output = new SymfonyStyle($input, $output);

        // check presence of force or dump-message
        if ($input->getOption('force') !== true && $input->getOption('dump-messages') !== true) {
            $output->error('You must choose one of --force or --dump-messages');

            return 1;
        }

        // check format
        $writer = $this->getContainer()->get('translation.writer');
        $supportedFormats = $writer->getFormats();
        if (!in_array($input->getOption('output-format'), $supportedFormats)) {
            $output->error(array('Wrong output format', 'Supported formats are '.implode(', ', $supportedFormats).'.'));

            return 1;
        }
        $kernel = $this->getContainer()->get('kernel');

        // Define Root Path to App folder
        $transPaths = array($kernel->getRootDir().'/Resources/');
        $currentName = 'app folder';

        // Override with provided Bundle info
        if (null !== $input->getArgument('bundle')) {
            try {
                $foundBundle = $kernel->getBundle($input->getArgument('bundle'));
                $transPaths = array(
                    $foundBundle->getPath().'/Resources/',
                    sprintf('%s/Resources/%s/', $kernel->getRootDir(), $foundBundle->getName()),
                );
                $currentName = $foundBundle->getName();
            } catch (\InvalidArgumentException $e) {
                // such a bundle does not exist, so treat the argument as path
                $transPaths = array($input->getArgument('bundle').'/Resources/');
                $currentName = $transPaths[0];

                if (!is_dir($transPaths[0])) {
                    throw new \InvalidArgumentException(sprintf('<error>"%s" is neither an enabled bundle nor a directory.</error>', $transPaths[0]));
                }
            }
        }

        $output->title('Symfony translation update command');
        $output->text(sprintf('Generating "<info>%s</info>" translation files for "<info>%s</info>"', $input->getArgument('locale'), $currentName));

        // load any messages from templates
        $extractedCatalogue = new MessageCatalogue($input->getArgument('locale'));
        $output->text('Parsing templates');
        $extractor = $this->getContainer()->get('translation.extractor');
        $extractor->setPrefix($input->getOption('prefix'));
        foreach ($transPaths as $path) {
            $path .= 'views';
            if (is_dir($path)) {
                $extractor->extract($path, $extractedCatalogue);
            }
        }

        // load any existing messages from the translation files
        $currentCatalogue = new MessageCatalogue($input->getArgument('locale'));
        $output->text('Loading translation files');
        $loader = $this->getContainer()->get('translation.loader');
        foreach ($transPaths as $path) {
            $path .= 'translations';
            if (is_dir($path)) {
                $loader->loadMessages($path, $currentCatalogue);
            }
        }

        // process catalogues
        $operation = $input->getOption('clean')
            ? new DiffOperation($currentCatalogue, $extractedCatalogue)
            : new MergeOperation($currentCatalogue, $extractedCatalogue);

        // Exit if no messages found.
        if (!count($operation->getDomains())) {
            $output->warning('No translation found.');

            return;
        }

        // show compiled list of messages
        if ($input->getOption('dump-messages') === true) {
            $output->newLine();
            foreach ($operation->getDomains() as $domain) {
                $output->section(sprintf('Displaying messages for domain <info>%s</info>:', $domain));
                $newKeys = array_keys($operation->getNewMessages($domain));
                $allKeys = array_keys($operation->getMessages($domain));
                $output->listing(array_merge(
                    array_diff($allKeys, $newKeys),
                    array_map(function ($id) {
                        return sprintf('<fg=green>%s</>', $id);
                    }, $newKeys),
                    array_map(function ($id) {
                        return sprintf('<fg=red>%s</>', $id);
                    }, array_keys($operation->getObsoleteMessages($domain)))
                ));
            }

            if ($input->getOption('output-format') == 'xlf') {
                $output->writeln('Xliff output version is <info>1.2</info>');
            }
        }

        if ($input->getOption('no-backup') === true) {
            $writer->disableBackup();
        }

        // save the files
        if ($input->getOption('force') === true) {
            $output->text('Writing files');

            $bundleTransPath = false;
            foreach ($transPaths as $path) {
                $path .= 'translations';
                if (is_dir($path)) {
                    $bundleTransPath = $path;
                }
            }

            if (!$bundleTransPath) {
                $bundleTransPath = end($transPaths).'translations';
            }

            $writer->writeTranslations($operation->getResult(), $input->getOption('output-format'), array('path' => $bundleTransPath, 'default_locale' => $this->getContainer()->getParameter('kernel.default_locale')));
        }

        $output->newLine();
        $output->success('Success');
    }
}
