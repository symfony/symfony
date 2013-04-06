<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Descriptor\Text;

use Symfony\Component\Console\Descriptor\DescriptorInterface;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
abstract class AbstractTextDescriptor implements DescriptorInterface
{
    /**
     * @var boolean
     */
    private $raw;

    /**
     * @param boolean $raw
     */
    public function __construct($raw = false)
    {
        $this->raw = $raw;
    }

    /**
     * {@inheritdoc}
     */
    public function configure(array $options)
    {
        if (isset($options['raw_text'])) {
            $this->raw = $options['raw_text'];
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function describe($object)
    {
        return $this->raw ? $this->getRawText($object) : $this->getFormattedText($object);
    }

    /**
     * Returns object's raw text description.
     *
     * @param object $object
     *
     * @return string
     */
    abstract public function getRawText($object);

    /**
     * Returns object's formatted text description.
     *
     * @param object $object
     *
     * @return string
     */
    abstract public function getFormattedText($object);

    /**
     * {@inheritdoc}
     */
    public function getFormat()
    {
        return 'txt';
    }

    /**
     * {@inheritdoc}
     */
    public function useFormatting()
    {
        return false;
    }
}
