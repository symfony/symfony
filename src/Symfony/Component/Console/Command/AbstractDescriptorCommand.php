<?php

namespace Symfony\Component\Console\Command;

use Symfony\Component\Console\Descriptor\DescriptorProvider;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

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
        $descriptorProvider = new DescriptorProvider();
        $this->supportedFormats = $descriptorProvider->getSupportedFormats();
        $this->setDefinition($this->createDefinition());
        $this->getHelperSet()->set(new DescriptorHelper($descriptorProvider));
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
}
