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
        foreach ($this as $elem) {
            return $elem;
        }
    }

    /**
     * Get the last element in the iterator.
     *
     * @return mixed
     */
    public function last()
    {
        foreach ($this as $elem) {
            continue;
        }

        return $elem;
    }
}
