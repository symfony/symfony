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
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class DescriptorProxy implements DescriptorInterface
{
    /**
     * @var array
     */
    private $defaultOptions = array();

    /**
     * @var DescriptorInterface[]
     */
    private $descriptors = array();

    /**
     * Constructor.
     *
     * Valid options are:
     * * format:        string       default rendering format
     * * namespace:     string|null  namespace for application description
     * * raw_text:      boolean      for raw text description
     * * json_encoding: integer      options for json encoding
     *
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        $this->defaultOptions = array_merge(array(
            'format'        => 'txt',
            'namespace'     => null,
            'raw_text'      => false,
            'json_encoding' => 0,
        ), $options);

        $this
            ->register('txt',  new TextDescriptor())
            ->register('xml',  new XmlDescriptor())
            ->register('json', new JsonDescriptor())
            ->register('md',   new MarkdownDescriptor())
        ;
    }

    /**
     * Registers a descriptor.
     *
     * @param string              $format
     * @param DescriptorInterface $descriptor
     *
     * @return DescriptorProxy
     */
    public function register($format, DescriptorInterface $descriptor)
    {
        $this->descriptors[$format] = $descriptor;

        return $this;
    }

    /**
     * Describes given object.
     *
     * @param object $object
     * @param array  $options
     *
     * @return mixed|string
     *
     * @throws \InvalidArgumentException
     */
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
     * {@inheritdoc}
     */
    public function describeInputArgument(InputArgument $argument, array $options = array())
    {
        $options = array_merge($this->defaultOptions, $options);

        return $this->getDescriptor($options['format'])->describeInputArgument($argument, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function describeInputOption(InputOption $option, array $options = array())
    {
        $options = array_merge($this->defaultOptions, $options);

        return $this->getDescriptor($options['format'])->describeInputOption($option, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function describeInputDefinition(InputDefinition $definition, array $options = array())
    {
        $options = array_merge($this->defaultOptions, $options);

        return $this->getDescriptor($options['format'])->describeInputDefinition($definition, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function describeCommand(Command $command, array $options = array())
    {
        $options = array_merge($this->defaultOptions, $options);

        return $this->getDescriptor($options['format'])->describeCommand($command, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function describeApplication(Application $application, array $options = array())
    {
        $options = array_merge($this->defaultOptions, $options);

        return $this->getDescriptor($options['format'])->describeApplication($application, $options);
    }

    /**
     * Returns a descriptor according to gievn format.
     *
     * @param string $format
     *
     * @throws \InvalidArgumentException
     *
     * @return DescriptorInterface
     */
    private function getDescriptor($format)
    {
        if (!isset($this->descriptors[$format])) {
            throw new \InvalidArgumentException(sprintf('Unsupported format "%s".', $format));
        }

        return $this->descriptors[$format];
    }
}
