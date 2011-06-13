<?php

namespace Symfony\Bundle\TwigBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Yaml\Yaml;

class TranslationUpdateCommand extends Command
{
    /**
     * Supported formats for output
     * 
     * @var array
     */
    private $supportedFormats = array('yml', 'xliff', 'php', 'pot');
    
    /**
     * Compiled catalogue of messages
     * @var  \Symfony\Component\Translation\MessageCatalogue
     */
    protected $catalogue;

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName('twig:translation:update')
            ->setDescription('Update the translation file')
            ->setDefinition(array(
                new InputArgument('locale', InputArgument::REQUIRED, 'The locale'),
                new InputArgument('bundle', InputArgument::REQUIRED, 'The bundle where to load the messages'),
                new InputOption(
                    'prefix', null, InputOption::VALUE_OPTIONAL,
                    'Override the default prefix', '__'
                ),
                new InputOption(
                    'output-format', null, InputOption::VALUE_OPTIONAL,
                    'Override the default output format (' . implode(', ', $this->supportedFormats) . ')', 'yml'
                ),
                new InputOption(
                    'source-lang', null, InputOption::VALUE_OPTIONAL,
                    'Set the source language attribute in xliff files', 'en'
                ),
                new InputOption(
                    'dump-messages', null, InputOption::VALUE_NONE,
                    'Should the messages be dumped in the console'
                ),
                new InputOption(
                    'force', null, InputOption::VALUE_NONE,
                    'Should the update be done'
                )
            ));
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('force') !== true && $input->getOption('dump-messages') !== true) {
            $output->writeln('<info>You must choose one of --force or --dump-messages</info>');
            return;
        }
        
        if (!in_array($input->getOption('output-format'), $this->supportedFormats)) {
            $output->writeln('<error>Wrong output format</error>');
            return;
        }

        // get bundle directory
        $foundBundle = $this->getApplication()->getKernel()->getBundle($input->getArgument('bundle'));
        $bundleTransPath = $foundBundle->getPath() . '/Resources/translations';
        $output->writeln(sprintf('Generating "<info>%s</info>" translation files for "<info>%s</info>"', $input->getArgument('locale'), $foundBundle->getName()));

        // create catalogue
        $catalogue = new MessageCatalogue($input->getArgument('locale'));
        
        // load any messages from templates
        $output->writeln('Parsing templates');
        $twigExtractor = $this->container->get('twig.translation.extractor');
        $twigExtractor->setPrefix($input->getOption('prefix'));
        $twigExtractor->extractMessages($foundBundle->getPath() . '/Resources/views/', $catalogue);
        
        // load any existing messages from the translation files
        $output->writeln('Parsing translation files');
        $fileExtractor = $this->container->get('twig.translation.extractor.file');
        $fileExtractor->extractMessages($bundleTransPath, $catalogue);
        
        // show compiled list of messages
        if($input->getOption('dump-messages') === true){
            foreach ($catalogue->getDomains() as $domain) {
                $output->writeln(sprintf("\nDisplaying messages for domain <info>%s</info>:\n", $domain));
                $output->writeln(Yaml::dump($catalogue->all($domain),10));
            }
            if($input->getOption('output-format') == 'xliff')
                $output->writeln('Xliff output version is <info>1.2/info>');
        }

        // save the files
        if($input->getOption('force') === true) {
            $output->writeln("Writing files");
            $fileWriter = $this->container->get('twig.translation.writer');
            $fileWriter->writeTranslations($catalogue, $bundleTransPath, $input->getOption('output-format'));
        }
    }
}
