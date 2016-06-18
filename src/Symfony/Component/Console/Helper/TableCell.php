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
    /**
     * @var string
     */
    private $value;

    /**
     * @var array
     */
    private $options = array(
        'rowspan' => 1,
        'colspan' => 1,
    );

    /**
     * @param string $value
     * @param array  $options
     */
    public function __construct($value = '', array $options = array())
    {
        $this->value = $value;

        // check option names
        if ($diff = array_diff(array_keys($options), array_keys($this->options))) {
            throw new InvalidArgumentException(sprintf('The TableCell does not support the following options: \'%s\'.', implode('\', \'', $diff)));
        }

        $this->options = array_merge($this->options, $options);

        if (0 >= (int) $this->options['colspan']) {
            throw new InvalidArgumentException(sprintf('The colspan value must be a positive integer ("%s" given).', $this->options['colspan']));
        }

        if (0 >= (int) $this->options['rowspan']) {
            throw new InvalidArgumentException(sprintf('The rowspan value must be a positive integer ("%s" given).', $this->options['rowspan']));
        }
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
        return (int) $this->options['colspan'];
    }

    /**
     * Gets number of rowspan.
     *
     * @return int
     */
    public function getRowspan()
    {
        return (int) $this->options['rowspan'];
    }
}
