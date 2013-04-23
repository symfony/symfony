<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Descriptor;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
abstract class Descriptor implements DescriptorInterface
{
    public function describe($object, array $options = array())
    {
        switch (true) {
            case $object instanceof InputArgument:
                return $this->describeInputArgument($object, $options);
            case $object instanceof InputOption:
                return $this->describeInputOption($object, $options);
            case $object instanceof InputDefinition:
                return $this->describeInputDefinition($object, $options);
            case $object instanceof Command:
                return $this->describeCommand($object, $options);
            case $object instanceof Application:
                return $this->describeApplication($object, $options);
        }

        throw new \InvalidArgumentException(sprintf('Object of type "%s" is not describable.', get_class($object)));
    }

    /**
     * Describes an InputArgument instance.
     *
     * @param InputArgument $argument
     * @param array         $options
     *
     * @return string|mixed
     */
    abstract protected function describeInputArgument(InputArgument $argument, array $options = array());

    /**
     * Describes an InputOption instance.
     *
     * @param InputOption $option
     * @param array       $options
     *
     * @return string|mixed
     */
    abstract protected function describeInputOption(InputOption $option, array $options = array());

    /**
     * Describes an InputDefinition instance.
     *
     * @param InputDefinition $definition
     * @param array           $options
     *
     * @return string|mixed
     */
    abstract protected function describeInputDefinition(InputDefinition $definition, array $options = array());

    /**
     * Describes a Command instance.
     *
     * @param Command $command
     * @param array   $options
     *
     * @return string|mixed
     */
    abstract protected function describeCommand(Command $command, array $options = array());

    /**
     * Describes an Application instance.
     *
     * @param Application $application
     * @param array       $options
     *
     * @return string|mixed
     */
    abstract protected function describeApplication(Application $application, array $options = array());
}
