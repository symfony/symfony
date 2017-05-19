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

use Symfony\Component\Console\Exception\InvalidArgumentException;

/**
 * @author Abdellatif Ait boudad <a.aitboudad@gmail.com>
 */
class TableCell
{
    private $value;
    private $rowspan;
    private $colspan;

    /**
     * @param string $value
     * @param int    $rowspan
     * @param int    $colspan
     *
     * @throws InvalidArgumentException If unsupported options are given (deprecated)
     */
    public function __construct($value = '', $rowspan = 1, $colspan = 1)
    {
        if (is_numeric($value) && !is_string($value)) {
            $value = (string) $value;
        }

        $this->value = $value;

        // deprecated options
        if (is_array($rowspan)) {
            @trigger_error('Passing an array to TableCell is deprecated since version 3.2 and will be removed in 4.0. Use the specific arguments instead.', E_USER_DEPRECATED);

            // check option names
            if ($diff = array_diff(array_keys($rowspan), array('rowspan', 'colspan'))) {
                throw new InvalidArgumentException(sprintf('The TableCell does not support the following options: \'%s\'.', implode('\', \'', $diff)));
            }
            $colspan = isset($rowspan['colspan']) ? (int) $rowspan['colspan'] : 1;
            $rowspan = isset($rowspan['rowspan']) ? (int) $rowspan['rowspan'] : 1;
        }

        $this->rowspan = abs($rowspan) ?: 1;
        $this->colspan = abs($colspan) ?: 1;
    }

    /**
     * Returns the cell value.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->value;
    }

    /**
     * Gets number of colspan.
     *
     * @return int
     */
    public function getColspan()
    {
        return $this->colspan;
    }

    /**
     * Gets number of rowspan.
     *
     * @return int
     */
    public function getRowspan()
    {
        return $this->rowspan;
    }
}
