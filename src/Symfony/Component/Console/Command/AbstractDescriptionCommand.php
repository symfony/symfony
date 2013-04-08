<?php

namespace Symfony\Component\Console\Command;

use Symfony\Component\Console\Descriptor\DescriptorProvider;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base class for description commands.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
abstract class AbstractDescriptionCommand extends Command
{
    /**
     * @var DescriptorProvider
     */
    private $provider;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->provider = new DescriptorProvider();
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
            new InputOption('format', null, InputOption::VALUE_REQUIRED, 'To output help in other formats'),
            new InputOption('raw', null, InputOption::VALUE_NONE, 'To output raw command list'),
        ));
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (null === $this->getHelperSet()) {
            $this->setHelperSet(new HelperSet());
        }

        $this->getHelperSet()->set(new DescriptorHelper($this->provider));
    }
}
