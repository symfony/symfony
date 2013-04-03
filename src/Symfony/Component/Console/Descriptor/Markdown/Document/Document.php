<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Descriptor\Markdown\Document;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class Document implements BlockInterface
{
    /**
     * @var BlockInterface[]
     */
    private $blocks = array();

    /**
     * @param BlockInterface[] $blocks
     */
    public function __construct(array $blocks = array())
    {
        foreach ($blocks as $block) {
            $this->add($block);
        }
    }

    /**
     * @param BlockInterface $block
     *
     * @return Document
     */
    public function add(BlockInterface $block)
    {
        $this->blocks[] = $block;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        return empty($this->blocks);
    }

    /**
     * {@inheritdoc}
     */
    public function format(Formatter $formatter)
    {
        return implode("\n\n", array_map(function (BlockInterface $block) use ($formatter) {
            return $block->format($formatter);
        }, array_filter($this->blocks, function (BlockInterface $block) {
            return !$block->isEmpty();
        })));
    }
}
