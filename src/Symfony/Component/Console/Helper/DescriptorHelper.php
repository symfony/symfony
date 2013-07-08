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

use Symfony\Component\Console\Descriptor\DescriptorInterface;
use Symfony\Component\Console\Descriptor\JsonDescriptor;
use Symfony\Component\Console\Descriptor\MarkdownDescriptor;
use Symfony\Component\Console\Descriptor\TextDescriptor;
use Symfony\Component\Console\Descriptor\XmlDescriptor;
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
    public function describe(OutputInterface $output, $object, $format = null, $raw = false, $namespace = null)
    {
        $options = array('raw_text' => $raw, 'format' => $format ?: 'txt', 'namespace' => $namespace);
        $type = !$raw && 'txt' === $options['format'] ? OutputInterface::OUTPUT_NORMAL : OutputInterface::OUTPUT_RAW;

        if (!isset($this->descriptors[$options['format']])) {
            throw new \InvalidArgumentException(sprintf('Unsupported format "%s".', $options['format']));
        }

        $descriptor = $this->descriptors[$options['format']];

        $output->writeln($descriptor->describe($object, $options), $type);
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
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'descriptor';
    }
}
