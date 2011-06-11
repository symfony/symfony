<?php

namespace Symfony\Bundle\TwigBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class TranslationUpdateCommand extends Command
{
    /**
     * Default domain for found trans blocks/filters
     *
     * @var string
     */
    private $defaultDomain = 'messages';
    
    /**
     * Supported formats for output
     * 
     * @var array
     */
    private $supportedFormats = array('yml', 'xliff', 'php', 'pot');
    
    /**
     * Supported formats for import
     * 
     * @var array
     */
    private $supportedLoaders = array('yml', 'xliff', 'php');
    
    /**
     * Prefix for newly found message ids
     *
     * @var string
     */
    protected $prefix;

    /**
     * Compiled catalogue of messages
     * @var  \Symfony\Component\Translation\MessageCatalogue
     */
    protected $messages;

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName('translation:update')
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
        $twig = $this->container->get('twig');
        $this->prefix = $input->getOption('prefix');

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

        $output->writeln('Parsing files.');

        // load any messages from templates
        $this->messages = new \Symfony\Component\Translation\MessageCatalogue($input->getArgument('locale'));
        $finder = new Finder();
        $files = $finder->files()->name('*.html.twig')->in($foundBundle->getPath() . '/Resources/views/');
        foreach ($files as $file) {
            $output->writeln(sprintf(' > parsing template <comment>%s</comment>', $file->getPathname()));
            $tree = $twig->parse($twig->tokenize(file_get_contents($file->getPathname())));
            $this->crawlNode($tree);
        }

        foreach($this->supportedLoaders as $format) {
            // load any existing translation files
            $finder = new Finder();
            $files = $finder->files()->name('*.' . $input->getArgument('locale') . $format)->in($bundleTransPath);
            foreach ($files as $file) {
                $output->writeln(sprintf(' > parsing translation <comment>%s</comment>', $file->getPathname()));
                $domain = substr($file->getFileName(), 0, strrpos($file->getFileName(), $input->getArgument('locale') . $attributes[0]['alias']) - 1);
                $loader = $this->container->get('translation.loader.' . $format);
                $this->messages->addCatalogue($loader->load($file->getPathname(), $input->getArgument('locale'), $domain));
            }
        }

        // show compiled list of messages
        if($input->getOption('dump-messages') === true){
            foreach ($this->messages->getDomains() as $domain) {
                $output->writeln(sprintf("\nDisplaying messages for domain <info>%s</info>:\n", $domain));
                $output->writeln(\Symfony\Component\Yaml\Yaml::dump($this->messages->all($domain),10));
            }
        }

        // save the files
        if($input->getOption('force') === true) {
            $output->writeln("\nWriting files.\n");
            $path = $foundBundle->getPath() . '/Resources/translations/';
            
            // get the right formatter
            $formatter = $this->container->get('twig.translation.formatter.' . $input->getOption('output-format'));
            
            foreach ($this->messages->getDomains() as $domain) {
                $file = $domain . '.' . $input->getArgument('locale') . '.' . $input->getOption('output-format');
                if (file_exists($path . $file)) {
                    copy($path . $file, $path . '~' . $file . '.bak');
                }
                $output->writeln(sprintf(' > generating <comment>%s</comment>', $path . $file));
                file_put_contents($path . $file, $formatter->format($this->messages->all($domain)));
            }
        }
    }

    /**
     * Recursive function that extract trans message from a twig tree
     *
     * @param \Twig_Node The twig tree root
     */
    private function crawlNode(\Twig_Node $node)
    {
        if ($node instanceof \Symfony\Bridge\Twig\Node\TransNode && !$node->getNode('body') instanceof \Twig_Node_Expression_GetAttr) {
            // trans block
            $domain = $node->getNode('domain')->getAttribute('value');
            $message = $node->getNode('body')->getAttribute('data');
            $this->messages->set($message, $this->prefix.$message, $domain);
        } else if ($node instanceof \Twig_Node_Print) {
            // trans filter (be carefull of how you chain your filters)
            $message = $this->extractMessage($node->getNode('expr'));
            $domain = $this->extractDomain($node->getNode('expr'));
            if($message !== null && $domain!== null) {
                 $this->messages->set($message, $this->prefix.$message, $domain);
            }
        } else {
            // continue crawling
            foreach ($node as $child) {
                if ($child != null) {
                    $this->crawlNode($child);
                }
            }
        }
    }

    /**
     * Extract a message from a \Twig_Node_Print
     * Return null if not a constant message
     *
     * @param \Twig_Node $node
     */
    private function extractMessage(\Twig_Node $node)
    {
        if($node->hasNode('node')) {
            return $this->extractMessage($node->getNode ('node'));
        }
        if($node instanceof \Twig_Node_Expression_Constant) {
            return $node->getAttribute('value');
        }

        return null;
    }

    /**
     * Extract a domain from a \Twig_Node_Print
     * Return null if no trans filter
     *
     * @param \Twig_Node $node
     */
    private function extractDomain(\Twig_Node $node)
    {
        // must be a filter node
        if(!$node instanceof \Twig_Node_Expression_Filter) {
            return null;
        }
        // is a trans filter
        if($node->getNode('filter')->getAttribute('value') == 'trans') {
            if($node->getNode('arguments')->hasNode(1)) {
                return $node->getNode('arguments')->getNode(1)->getAttribute('value');
            } else {
                return $this->defaultDomain;
            }
        }

        return $this->extractDomain($node->getNode('node'));
    }

}
