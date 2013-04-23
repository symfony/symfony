<?php

namespace Symfony\Component\Console\Descriptor;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

/**
 * Descriptor interface.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
interface DescriptorInterface
{
    /**
     * @param InputArgument $argument
     *
     * @param array $options
     *
     * @return string|mixed
     */
    public function describeInputArgument(InputArgument $argument, array $options = array());

    /**
     * @param InputOption $option
     *
     * @param array $options
     *
     * @return string|mixed
     */
    public function describeInputOption(InputOption $option, array $options = array());

    /**
     * @param InputDefinition $definition
     *
     * @param array $options
     *
     * @return string|mixed
     */
    public function describeInputDefinition(InputDefinition $definition, array $options = array());

    /**
     * @param Command $command
     *
     * @param array $options
     *
     * @return string|mixed
     */
    public function describeCommand(Command $command, array $options = array());

    /**
     * @param Application $application
     *
     * @param array $options
     *
     * @return string|mixed
     */
    public function describeApplication(Application $application, array $options = array());
}
