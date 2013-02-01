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
 * A command that parses templates to extract translation messages and dumps them.
 *
 * @author Michel Salib <michelsalib@hotmail.com>
 * @author Toni Uebernickel <tuebernickel@gmail.com>
 *
 * @method \Symfony\Bundle\FrameworkBundle\Console\Application getApplication()
 */
class TranslationUpdateCommand extends ContainerAwareCommand
{
    /**
     * Compiled catalogue of messages.
     *
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
                new InputArgument(
                    'bundle', InputArgument::OPTIONAL ^ InputArgument::IS_ARRAY,
                    'The bundle(s) where to load the messages from', array()
                ),

                new InputOption(
                    'prefix', null, InputOption::VALUE_OPTIONAL,
                    'Override the default prefix', '__'
                ),
                new InputOption(
                    'dump-messages', null, InputOption::VALUE_NONE,
                    'Should the messages be dumped in the console'
                ),
                new InputOption(
                    'force', null, InputOption::VALUE_NONE,
                    'Should the update be done'
                ),
                new InputOption(
                    'include-global-trans', null, InputOption::VALUE_NONE,
                    'Include the app-wide translation files'
                ),
                new InputOption(
                    'include-global-views', null, InputOption::VALUE_NONE,
                    'Include the app-wide template files'
                ),
                new InputOption(
                    'output-dir', null, InputOption::VALUE_REQUIRED,
                    'Directory to write the translation files into'
                ),
                new InputOption(
                    'output-format', null, InputOption::VALUE_OPTIONAL,
                    'Override the default output format', 'yml'
                ),
                new InputOption(
                    'all-bundles', null, InputOption::VALUE_NONE,
                    'Load messages for all bundles'
                ),
                new InputOption(
                    'exclude-bundle', null, InputOption::VALUE_REQUIRED ^ InputOption::VALUE_IS_ARRAY,
                    'Exclude the given bundle(s)', array()
                ),
            ))
            ->setDescription('Updates the translation file')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command extract translation strings from templates
of a given bundle. It can display them or merge the new ones into the translation files.
When new translation strings are found it can automatically add a prefix to the translation
message.

<info>php %command.full_name% --dump-messages en AcmeBundle</info>
<info>php %command.full_name% --force --prefix="new_" fr AcmeBundle</info>

The output directory defaults to the bundles translation directory, if only one is present
or the app-wide directory, if there is no bundle specified or <info>--include-global-trans</info> is set.

<info>php %command.full_name% --include-global-trans en AcmeBundle</info>

You can update translation messages for multiple or all bundles at once.
If you do so, specify the <info>--output-dir</info> in case the <info>--output-format</info> is file based.

<info>php %command.full_name% --all-bundles --output-format=xml --output-dir="./app/Resources/translations" en</info>
<info>php %command.full_name% --all-bundles --output-format=pdo en</info>

You may exclude certain bundles by setting <info>--exclude-bundle</info> options.

<info>php %command.full_name% --all-bundles --exclude-bundle=MopaBootstrapBundle --include-global-trans --output-format=pdo en</info>
EOF
            )
        ;
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // check presence of force or dump-messages
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

        $globalDirectory = $this->getApplication()->getKernel()->getRootDir().'/Resources/translations';
        $outputDirectory = $globalDirectory;

        $translationDirectories = array();
        $templateDirectories = array();

        $bundles = $this->getBundles($input);
        // Add the directories for the provided bundles to their respective lists.
        foreach ($bundles as $eachBundle) {
            // The directories may not exist, if the translations are being loaded from Loaders not being file based.
            if (is_dir($eachBundle->getPath().'/Resources/translations')) {
                $translationDirectories[] = $eachBundle->getPath().'/Resources/translations';
                $outputDirectory = $eachBundle->getPath().'/Resources/translations';
            }

            // The directories may not exist, if the templates are being loaded from Loaders not being file based.
            if (is_dir($eachBundle->getPath().'/Resources/views')) {
                $templateDirectories[] = $eachBundle->getPath().'/Resources/views';
            }

            $output->writeln(sprintf('Generating "<info>%s</info>" translation messages for "<info>%s</info>"', $input->getArgument('locale'), $eachBundle->getName()));
        }

        // Include global directories, if specified.
        if ($input->getOption('include-global-trans')) {
            $translationDirectories[] = $globalDirectory;

            // Default to the global directory, if there are global translation files involved.
            $outputDirectory = $globalDirectory;
        }
        if ($input->getOption('include-global-views')) {
            $templateDirectories[] = $this->getApplication()->getKernel()->getRootDir().'/Resources/views';
        }

        // The final catalogue that will be dumped and/or written.
        $catalogue = new MessageCatalogue($input->getArgument('locale'));

        // Extract the messages from templates.
        $output->writeln('Extracting translation messages from templates.');
        $extractor = $this->getContainer()->get('translation.extractor');
        $extractor->setPrefix($input->getOption('prefix'));
        foreach ($templateDirectories as $eachTemplateDir) {
            $extractor->extract($eachTemplateDir, $catalogue);
        }

        // Load any existing messages from the registered loader(s).
        $output->writeln('Loading existing translation data.');
        $loader = $this->getContainer()->get('translation.loader');
        foreach ($translationDirectories as $eachTransDir) {
            $loader->loadMessages($eachTransDir, $catalogue);
        }

        // Dump the compiled list of messages.
        if ($input->getOption('dump-messages') === true) {
            foreach ($catalogue->getDomains() as $domain) {
                $output->writeln(sprintf("\nDisplaying messages for domain <info>%s</info>:\n", $domain));
                $output->writeln(Yaml::dump($catalogue->all($domain), 10));
            }
            if ($input->getOption('output-format') == 'xliff') {
                $output->writeln('Xliff output version is <info>1.2</info>');
            }
        }

        // If there are more than one bundle, use the global directory.
        if (1 < count($bundles)) {
            $outputDirectory = $globalDirectory;
        }

        // The user overwrites the output directory.
        if ($input->getOption('output-dir')) {
            $outputDirectory = $input->getOption('output-dir');
        }

        // Write the translation messages using the prodived TranslationDumper (format).
        if ($input->getOption('force') === true) {
            $output->writeln('Writing translation messages.');
            $writer->writeTranslations($catalogue, $input->getOption('output-format'), array('path' => $outputDirectory));
        }

        return 0;
    }

    /**
     * Return the list of bundles to load messages from.
     *
     * The list is indexed by the name of the respective bundle.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *
     * @return \Symfony\Component\HttpKernel\Bundle\BundleInterface[]
     */
    protected function getBundles(InputInterface $input)
    {
        $bundles = array();

        // Bundles that have been specified.
        if ($input->getArgument('bundle')) {
            foreach ($input->getArgument('bundle') as $eachBundleName) {
                $bundles[$this->getApplication()->getKernel()->getBundle($eachBundleName)->getName()] = $this->getApplication()->getKernel()->getBundle($eachBundleName);
            }
        }

        // All bundles are requested.
        if ($input->getOption('all-bundles')) {
            foreach ($this->getApplication()->getKernel()->getBundles() as $eachBundle) {
                $bundles[$eachBundle->getName()] = $eachBundle;
            }
        }

        // Remove excluded bundles.
        foreach ($input->getOption('exclude-bundle') as $eachExcludedBundle) {
            unset($bundles[$eachExcludedBundle]);
        }

        return $bundles;
    }
}
