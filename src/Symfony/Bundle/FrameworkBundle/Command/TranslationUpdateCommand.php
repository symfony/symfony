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
            ->setHelp(<<<EOF
The <info>%command.name%</info> command extract translation strings from templates
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
        // check presence of force or dump-message
        if ($input->getOption('force') !== true && $input->getOption('dump-messages') !== true) {
            $output->writeln('<info>You must choose one of --force or --dump-messages</info>');

            return 1;
        }

        // check format
        $writer = $this->getContainer()->get('translation.writer');
        $supportedFormats = $writer->getFormats();
        if (!in_array($input->getOption('output-format'), $supportedFormats)) {
            $output->writeln('<error>Wrong output format</error>');
            $output->writeln('Supported formats are '.implode(', ', $supportedFormats).'.');

            return 1;
        }
        $kernel = $this->getContainer()->get('kernel');

        // Define Root Path to App folder
        $rootPath = $kernel->getRootDir();
        $currentName = "app folder";

        // Override with provided Bundle info
        if (null !== $input->getArgument('bundle')) {
            try {
                $foundBundle = $kernel->getBundle($input->getArgument('bundle'));
                $rootPath = $foundBundle->getPath();
                $currentName = $foundBundle->getName();
            } catch (\InvalidArgumentException $e) {
                // such a bundle does not exist, so treat the argument as path
                $rootPath = $input->getArgument('bundle');
                $currentName = $rootPath;

                if (!is_dir($rootPath)) {
                    throw new \InvalidArgumentException(sprintf('<error>"%s" is neither an enabled bundle nor a directory.</error>', $rootPath));
                }
            }
        }

        // get bundle directory
        $translationsPath = $rootPath.'/Resources/translations';
        $output->writeln(sprintf('Generating "<info>%s</info>" translation files for "<info>%s</info>"', $input->getArgument('locale'), $currentName));

        // load any messages from templates
        $extractedCatalogue = new MessageCatalogue($input->getArgument('locale'));
        $output->writeln('Parsing templates');
        $extractor = $this->getContainer()->get('translation.extractor');
        $extractor->setPrefix($input->getOption('prefix'));
        $extractor->extract($rootPath.'/Resources/views/', $extractedCatalogue);

        // load any existing messages from the translation files
        $currentCatalogue = new MessageCatalogue($input->getArgument('locale'));
        $output->writeln('Loading translation files');
        $loader = $this->getContainer()->get('translation.loader');
        $loader->loadMessages($translationsPath, $currentCatalogue);

        // process catalogues
        $operation = $input->getOption('clean')
            ? new DiffOperation($currentCatalogue, $extractedCatalogue)
            : new MergeOperation($currentCatalogue, $extractedCatalogue);

        // Exit if no messages found.
        if (!count($operation->getDomains())) {
            $output->writeln("\n<comment>No translation found.</comment>");

            return;
        }

        // show compiled list of messages
        if ($input->getOption('dump-messages') === true) {
            foreach ($operation->getDomains() as $domain) {
                $output->writeln(sprintf("\nDisplaying messages for domain <info>%s</info>:\n", $domain));
                $newKeys = array_keys($operation->getNewMessages($domain));
                $allKeys = array_keys($operation->getMessages($domain));
                foreach (array_diff($allKeys, $newKeys) as $id) {
                    $output->writeln($id);
                }
                foreach ($newKeys as $id) {
                    $output->writeln(sprintf('<fg=green>%s</>', $id));
                }
                foreach (array_keys($operation->getObsoleteMessages($domain)) as $id) {
                    $output->writeln(sprintf('<fg=red>%s</>', $id));
                }
            }

            if ($input->getOption('output-format') == 'xliff') {
                $output->writeln('Xliff output version is <info>1.2</info>');
            }
        }

        if ($input->getOption('no-backup') === true) {
            $writer->disableBackup();
        }

        // save the files
        if ($input->getOption('force') === true) {
            $output->writeln('Writing files');
            $writer->writeTranslations($operation->getResult(), $input->getOption('output-format'), array('path' => $translationsPath, 'default_locale' => $this->getContainer()->getParameter('kernel.default_locale')));
        }
    }
}
