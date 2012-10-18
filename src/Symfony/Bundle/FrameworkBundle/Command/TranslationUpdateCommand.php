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

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Yaml\Yaml;

/**
 * A command that parse templates to extract translation messages and add them into the translation files.
 *
 * @author Michel Salib <michelsalib@hotmail.com>
 */
class TranslationUpdateCommand extends ContainerAwareCommand
{
    /**
     * Compiled catalogue of messages.
     * @var MessageCatalogue
     */
    protected $catalogue;

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName('translation:update')
            ->setDefinition(array(
                new InputArgument('locale', InputArgument::REQUIRED, 'The locale'),
                new InputArgument('bundle', InputArgument::REQUIRED, 'The bundle where to load the messages'),
                new InputOption(
                    'prefix', null, InputOption::VALUE_OPTIONAL,
                    'Override the default prefix', '__'
                ),
                new InputOption(
                    'output-format', null, InputOption::VALUE_OPTIONAL,
                    'Override the default output format', 'yml'
                ),
                new InputOption(
                    'dump-messages', null, InputOption::VALUE_NONE,
                    'Should the messages be dumped in the console'
                ),
                new InputOption(
                    'force', null, InputOption::VALUE_NONE,
                    'Should the update be done'
                )
            ))
            ->setDescription('Updates the translation file')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command extract translation strings from templates
of a given bundle. It can display them or merge the new ones into the translation files.
When new translation strings are found it can automatically add a prefix to the translation
message.

<info>php %command.full_name% --dump-messages en AcmeBundle</info>
<info>php %command.full_name% --force --prefix="new_" fr AcmeBundle</info>
EOF
            )
        ;
    }

    /**
     * {@inheritDoc}
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

        // get bundle directory
        $foundBundle = $this->getApplication()->getKernel()->getBundle($input->getArgument('bundle'));
        $bundleTransPath = $foundBundle->getPath().'/Resources/translations';
        $output->writeln(sprintf('Generating "<info>%s</info>" translation files for "<info>%s</info>"', $input->getArgument('locale'), $foundBundle->getName()));

        // create catalogue
        $catalogue = new MessageCatalogue($input->getArgument('locale'));

        // load any messages from templates
        $output->writeln('Parsing templates');
        $extractor = $this->getContainer()->get('translation.extractor');
        $extractor->setPrefix($input->getOption('prefix'));
        $extractor->extract($foundBundle->getPath().'/Resources/views/', $catalogue);

        // load any existing messages from the translation files
        $output->writeln('Loading translation files');
        $loader = $this->getContainer()->get('translation.loader');
        $loader->loadMessages($bundleTransPath, $catalogue);

        // show compiled list of messages
        if ($input->getOption('dump-messages') === true) {
            foreach ($catalogue->getDomains() as $domain) {
                $output->writeln(sprintf("\nDisplaying messages for domain <info>%s</info>:\n", $domain));
                $output->writeln(Yaml::dump($catalogue->all($domain), 10));
            }
            if ($input->getOption('output-format') == 'xliff') {
                $output->writeln('Xliff output version is <info>1.2</info>');
            }
        }

        // save the files
        if ($input->getOption('force') === true) {
            $output->writeln('Writing files');
            $writer->writeTranslations($catalogue, $input->getOption('output-format'), array('path' => $bundleTransPath));
        }
    }
}
