<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Helper;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Descriptor\DescriptorInterface;
use Symfony\Component\Console\Descriptor\DescriptorProxy;
use Symfony\Component\Console\Descriptor\JsonDescriptor;
use Symfony\Component\Console\Descriptor\MarkdownDescriptor;
use Symfony\Component\Console\Descriptor\TextDescriptor;
use Symfony\Component\Console\Descriptor\XmlDescriptor;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This class adds helper method to describe objects in various formats.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class DescriptorHelper extends Helper
{
    /**
     * @var DescriptorInterface[]
     */
    private $descriptors = array();

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this
            ->register('txt',  new TextDescriptor())
            ->register('xml',  new XmlDescriptor())
            ->register('json', new JsonDescriptor())
            ->register('md',   new MarkdownDescriptor())
        ;
    }

    /**
     * Describes an object if supported.
     *
     * @param OutputInterface $output
     * @param object          $object
     * @param string          $format
     * @param boolean         $raw
     */
    public function describe(OutputInterface $output, $object, $format = null, $raw = false)
    {
        $options = array('raw_text' => $raw, 'format' => $format ?: 'txt');
        $type = !$raw && 'txt' === $options['format'] ? OutputInterface::OUTPUT_NORMAL : OutputInterface::OUTPUT_RAW;
        $output->writeln($this->getDescription($object, $options), $type);
    }

    /**
     * Registers a descriptor.
     *
     * @param string              $format
     * @param DescriptorInterface $descriptor
     *
     * @return DescriptorHelper
     */
    public function register($format, DescriptorInterface $descriptor)
    {
        $this->descriptors[$format] = $descriptor;

        return $this;
    }

    /**
     * Gets the descriptor associated with the given object.
     *
     * @param object $object  The object to describe
     * @param array  $options The options
     *
     * @return DescriptorInterface The descritptor to use for the given object
     *
     * @throws \InvalidArgumentException when no descriptor supports the given object or when the format is not supported
     */
    private function getDescription($object, array $options)
    {
        if (!isset($this->descriptors[$options['format']])) {
            throw new \InvalidArgumentException(sprintf('Unsupported format "%s".', $options['format']));
        }

        $descriptor = $this->descriptors[$options['format']];

        switch (true) {
            case $object instanceof InputArgument:
                return $descriptor->describeInputArgument($object, $options);
            case $object instanceof InputOption:
                return $descriptor->describeInputOption($object, $options);
            case $object instanceof InputDefinition:
                return $descriptor->describeInputDefinition($object, $options);
            case $object instanceof Command:
                return $descriptor->describeCommand($object, $options);
            case $object instanceof Application:
                return $descriptor->describeApplication($object, $options);
        }

        throw new \InvalidArgumentException(sprintf('Object of type "%s" is not describable.', get_class($object)));
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'descriptor';
    }
}
