<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Event;

class FilterDataEvent extends DataEvent
{
    /**
     * Allows updating with some filtered data
     *
     * @param mixed $data
     */
    public function setData($data)
    {
        $this->data = $data;
    }
}
