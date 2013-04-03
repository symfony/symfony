<?php

namespace Symfony\Component\Console\Command;

use Symfony\Component\Console\Descriptor\DescriptorProvider;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base class for descriptor commands.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
abstract class AbstractDescriptorCommand extends Command
{
    /**
     * @var array
     */
    private $supportedFormats;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setDefinition($this->createDefinition());
    }

    /**
     * Creates command definition.
     *
     * @return InputDefinition
     */
    protected function createDefinition()
    {
        return new InputDefinition(array(
            new InputOption('format', null, InputOption::VALUE_OPTIONAL, 'Output format ('.implode(', ', $this->supportedFormats).')'),
            new InputOption('raw', null, InputOption::VALUE_NONE, 'To output raw command list'),
        ));
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $descriptorProvider = new DescriptorProvider();
        $this->supportedFormats = $descriptorProvider->getSupportedFormats();
        $this->setDefinition($this->createDefinition());
        $this->getHelperSet()->set(new DescriptorHelper($descriptorProvider));
    }
}
