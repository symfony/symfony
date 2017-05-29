<?php

namespace Symfony\Component\Finder\Iterator;

/**
 * @author Ramon Kleiss <ramonkleiss@gmail.com>
 * @author Royi Eltink
 */
class FinderIterator extends \AppendIterator
{
    /**
     * Get the first element in the iterator.
     *
     * @return mixed
     */
    public function first()
    {
        foreach ($this as $element) {
            return $element;
        }

        return;
    }

    /**
     * Get the last element in the iterator.
     *
     * @return mixed
     */
    public function last()
    {
        $elements = array_values(iterator_to_array($this));

        return empty($elements) ? null : $elements[count($elements) - 1];
    }
}
