<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Console\Descriptor;

use Symphony\Component\Console\Application;
use Symphony\Component\Console\Command\Command;
use Symphony\Component\Console\Input\InputArgument;
use Symphony\Component\Console\Input\InputDefinition;
use Symphony\Component\Console\Input\InputOption;
use Symphony\Component\Console\Output\OutputInterface;
use Symphony\Component\Console\Exception\InvalidArgumentException;

/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
abstract class Descriptor implements DescriptorInterface
{
    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * {@inheritdoc}
     */
    public function describe(OutputInterface $output, $object, array $options = array())
    {
        $this->output = $output;

        switch (true) {
            case $object instanceof InputArgument:
                $this->describeInputArgument($object, $options);
                break;
            case $object instanceof InputOption:
                $this->describeInputOption($object, $options);
                break;
            case $object instanceof InputDefinition:
                $this->describeInputDefinition($object, $options);
                break;
            case $object instanceof Command:
                $this->describeCommand($object, $options);
                break;
            case $object instanceof Application:
                $this->describeApplication($object, $options);
                break;
            default:
                throw new InvalidArgumentException(sprintf('Object of type "%s" is not describable.', get_class($object)));
        }
    }

    /**
     * Writes content to output.
     *
     * @param string $content
     * @param bool   $decorated
     */
    protected function write($content, $decorated = false)
    {
        $this->output->write($content, false, $decorated ? OutputInterface::OUTPUT_NORMAL : OutputInterface::OUTPUT_RAW);
    }

    /**
     * Describes an InputArgument instance.
     *
     * @return string|mixed
     */
    abstract protected function describeInputArgument(InputArgument $argument, array $options = array());

    /**
     * Describes an InputOption instance.
     *
     * @return string|mixed
     */
    abstract protected function describeInputOption(InputOption $option, array $options = array());

    /**
     * Describes an InputDefinition instance.
     *
     * @return string|mixed
     */
    abstract protected function describeInputDefinition(InputDefinition $definition, array $options = array());

    /**
     * Describes a Command instance.
     *
     * @return string|mixed
     */
    abstract protected function describeCommand(Command $command, array $options = array());

    /**
     * Describes an Application instance.
     *
     * @return string|mixed
     */
    abstract protected function describeApplication(Application $application, array $options = array());
}
