<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Descriptor\Markdown;

use Symfony\Component\Console\Descriptor\DescriptorInterface;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
abstract class AbstractMarkdownDescriptor implements DescriptorInterface
{
    /**
     * @var int
     */
    private $maxWidth;

    /**
     * @param int $maxWidth
     */
    public function __construct($maxWidth = 120)
    {
        $this->maxWidth = $maxWidth;
    }

    /**
     * {@inheritdoc}
     */
    public function configure(array $options)
    {
        $this->maxWidth = $options['markdown_width'];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function describe($object, $raw = false)
    {
        return $this->getDocument($object)->format(new Document\Formatter($this->maxWidth));
    }

    /**
     * Returns object document to format.
     *
     * @param object $object
     *
     * @return Document\Document
     */
    abstract public function getDocument($object);

    /**
     * {@inheritdoc}
     */
    public function getFormat()
    {
        return 'md';
    }

    /**
     * {@inheritdoc}
     */
    public function useFormatting()
    {
        return false;
    }
}
