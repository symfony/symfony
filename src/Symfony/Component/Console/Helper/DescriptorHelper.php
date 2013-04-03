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

use Symfony\Component\Console\Descriptor\DescriptorProvider;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This class adds helper method to describe objects in various formats.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class DescriptorHelper extends Helper
{
    /**
     * @var DescriptorProvider
     */
    private $provider;

    /**
     * Constructor.
     *
     * @param DescriptorProvider $provider
     */
    public function __construct(DescriptorProvider $provider)
    {
        $this->provider = $provider;
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
        $format = $format ?: $this->provider->getDefaultFormat();
        $descriptor = $this->provider->get($object, $format);
        $type = $raw && $descriptor->useFormatting() ? OutputInterface::OUTPUT_NORMAL : OutputInterface::OUTPUT_RAW;

        $output->writeln($descriptor->describe($object, $format, $raw), $type);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'descriptor';
    }
}
