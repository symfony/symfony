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
use Symfony\Component\Finder\Finder;

/**
 * A command that parse templates to extract translation messages and add them into the translation files.
 *
 * @author Michel Salib <michelsalib@hotmail.com>
 * @author Adam Prager <prager.adam87@gmail.com>
 */
class TranslationUpdateCommand extends ContainerAwareCommand
{
    /**
     * Compiled catalogue of messages.
     * @var MessageCatalogue
     */
    protected $catalogue;
    
    protected $writer;
    
    protected $output;
    
    protected $input;

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
                ),
                new InputOption(
                    'common-catalogue', null, InputOption::VALUE_NONE,
                    'Use a common catalogue'
                ),
                new InputOption(
                    'form-label-fallback', null, InputOption::VALUE_OPTIONAL,
                    'Which attribute to fall back to when label is not set directly', 'name'
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
<info>php %command.full_name% --force --common-catalogue fr Acme</info>
EOF
            )
        ;
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        
        // check presence of force or dump-message
        if ($this->input->getOption('force') !== true && $this->input->getOption('dump-messages') !== true) {
            $this->output->writeln('<info>You must choose one of --force or --dump-messages</info>');

            return 1;
        }

        // check format
        $this->writer = $this->getContainer()->get('translation.writer');
        $supportedFormats = $this->writer->getFormats();
        if (!in_array($this->input->getOption('output-format'), $supportedFormats)) {
            $this->output->writeln('<error>Wrong output format</error>');
            $this->output->writeln('Supported formats are '.implode(', ', $supportedFormats).'.');

            return 1;
        }
        
        try {
            $foundBundle = $this->getApplication()->getKernel()->getBundle($input->getArgument('bundle'));
            
            $this->updateBundleTranslations($foundBundle);
        } catch (\InvalidArgumentException $e) {
            $foundBundles = $this->getApplication()->getKernel()->getBundles();

            foreach($foundBundles as $key => $foundBundle) {
                $namespace = explode('\\', $foundBundle->getNamespace());

                if($namespace[0] != $this->input->getArgument('bundle')) {
                   unset($foundBundles[$key]);
                }
            }

            if (count($foundBundles) == 0 ) {
                $this->output->writeln(sprintf('No bundle found in namespace: "<info>%s</info>"', $this->input->getArgument('bundle')));
            }
            else if (count($foundBundles) > 1 && $this->input->getOption('common-catalogue') == true) {
                $this->updateNamespaceTranslations($foundBundles);
            }
            else {
                foreach ($foundBundles as $foundBundle) {
                    $this->updateBundleTranslations($foundBundle);
                }
            }
        }
    }
    
    protected function updateNamespaceTranslations($foundBundles)
    {
        $this->output->writeln(sprintf('Generating "<info>%s</info>" translation files for "<info>%s</info>"', $this->input->getArgument('locale'), $this->input->getArgument('bundle')));

        $catalogue = $this->createCatalogue();

        foreach ($foundBundles as $foundBundle) {
            $this->loadMessages($catalogue, $foundBundle);
        }
        
        // important to use separate foreaches, first import all messages, then add translations
        foreach ($foundBundles as $foundBundle) {
            $this->loadExistingTranslations($catalogue, $foundBundle->getPath().'/Resources/translations');
        }
        
        if(!file_exists($this->getApplication()->getKernel()->getRootDir().'/Resources/translations')) {
            mkdir($this->getApplication()->getKernel()->getRootDir().'/Resources/translations', '0644');
        }

        $this->loadExistingTranslations($catalogue, $this->getApplication()->getKernel()->getRootDir().'/Resources/translations');
        $this->showCompiledListOfMessages($catalogue, $this->getApplication()->getKernel()->getRootDir().'/Resources/translations');
    }
    
    protected function updateBundleTranslations($foundBundle)
    {
        $this->output->writeln(sprintf('Generating "<info>%s</info>" translation files for "<info>%s</info>"', $this->input->getArgument('locale'), $foundBundle->getName()));

        $catalogue = $this->createCatalogue();
        $this->loadMessages($catalogue, $foundBundle);
        $this->loadExistingTranslations($catalogue, $foundBundle->getPath().'/Resources/translations');
        $this->showCompiledListOfMessages($catalogue, $foundBundle->getPath().'/Resources/translations');
    }
    
    protected function createCatalogue()
    {
        return new MessageCatalogue($this->input->getArgument('locale'));
    }
    
    protected function loadMessages($catalogue, $foundBundle)
    {
        // load twig and php
        $extractor = $this->getContainer()->get('translation.extractor');
        $extractor->setPrefix($this->input->getOption('prefix'));
        $extractor->extract($foundBundle->getPath(), $catalogue);
        
        // load form labels
        $finder = new Finder();
        if(file_exists($foundBundle->getPath().'/Form')) {
            $files = $finder->files()->name('*.php')->in($foundBundle->getPath().'/Form');
            foreach ($files as $file) {
                $class = $foundBundle->getNamespace().'\\Form\\'.str_replace('.php', '', str_replace('/', '\\', $file->getRelativePathname()));

                $reflection = new \ReflectionClass($class);

                if ($reflection->getConstructor() == NULL || $reflection->getConstructor()->getNumberOfRequiredParameters() == 0) {
                    if ($reflection->isSubclassOf('Symfony\Component\Form\AbstractType') && !$reflection->isAbstract()) {                        
                        $form = $this->getApplication()->getKernel()->getContainer()->get('form.factory')->create(new $class())->createView();

                        foreach ($form as $field) {
                            $vars = $field->getVars();
                            
                            if ($vars['name'] == '_token') {
                                continue;
                            }

                            $message = $vars['label'] == NULL
                                            ? str_replace('_', '.', $vars[$this->input->getOption('form-label-fallback')])
                                            : $vars['label'];

                            $catalogue->set($message, $this->input->getOption('prefix').$message);
                        }
                    }
                }
            }
        }
    }
    
    protected function loadExistingTranslations($catalogue, $path)
    {
        $loader = $this->getContainer()->get('translation.loader');
        $loader->loadMessages($path, $catalogue);
    }
    
    protected function showCompiledListOfMessages($catalogue, $path)
    {
        // show compiled list of messages
        if ($this->input->getOption('dump-messages') === true) {
            foreach ($catalogue->getDomains() as $domain) {
                $this->output->writeln(sprintf("\nDisplaying messages for domain <info>%s</info>:\n", $domain));
                $this->output->writeln(Yaml::dump($catalogue->all($domain), 10));
            }
            if ($this->input->getOption('output-format') == 'xliff') {
                $this->output->writeln('Xliff output version is <info>1.2</info>');
            }
        }

        // save the files
        if ($this->input->getOption('force') === true) {
            $this->output->writeln(sprintf("Writing files to: <info>%s</info> in <info>%s</info> format", $path, $this->input->getOption('output-format')));
            $this->writer->writeTranslations($catalogue, $this->input->getOption('output-format'), array('path' => $path));
        }
    }
}
