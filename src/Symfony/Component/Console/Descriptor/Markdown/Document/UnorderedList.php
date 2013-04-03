<?php

namespace Symfony\Component\Console\Descriptor\Markdown\Document;

/**
 * Document unordered list.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class UnorderedList implements BlockInterface
{
    /**
     * @var array
     */
    private $items;

    /**
     * @param array $items
     */
    public function __construct(array $items = array())
    {
        $this->items = $items;
    }

    /**
     * @param $item
     *
     * @return UnorderedList
     */
    public function push($item)
    {
        $this->items[] = $item;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        return empty($this->items);
    }

    /**
     * {@inheritdoc}
     */
    public function format(Formatter $formatter)
    {
        $content = array();
        foreach ($this->items as $item) {
            foreach ($formatter->clip($item, 2) as $index => $line) {
                $content[] = (0 === $index ? '* ' : '  ').$line;
            }
        }

        return implode("\n", $content);
    }
}
