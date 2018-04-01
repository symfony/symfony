<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Console\Helper;

/**
 * Marks a row as being a separator.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class TableSeparator extends TableCell
{
    public function __construct(array $options = array())
    {
        parent::__construct('', $options);
    }
}
