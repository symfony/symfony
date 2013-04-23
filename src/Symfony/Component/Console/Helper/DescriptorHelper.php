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
use Symfony\Component\Console\Descriptor\DescriptorProxy;
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
     * @var DescriptorProxy
     */
    private $descriptor;

    /**
     * Constructor.
     *
     * @param DescriptorProxy $descriptor
     */
    public function __construct(DescriptorProxy $descriptor = null)
    {
        $this->descriptor = $descriptor ?: new DescriptorProxy();
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
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'descriptor';
    }

    private function getDescription($object, array $options)
    {
        switch (true) {
            case $object instanceof InputArgument:
                return $this->descriptor->describeInputArgument($object, $options);
            case $object instanceof InputOption:
                return $this->descriptor->describeInputOption($object, $options);
            case $object instanceof InputDefinition:
                return $this->descriptor->describeInputDefinition($object, $options);
            case $object instanceof Command:
                return $this->descriptor->describeCommand($object, $options);
            case $object instanceof Application:
                return $this->descriptor->describeApplication($object, $options);
        }

        throw new \InvalidArgumentException(sprintf('Object of type "%s" is not describable.', get_class($object)));
    }
}
