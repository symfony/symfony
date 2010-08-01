<?php

namespace Symfony\Components\OutputEscaper;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Abstract output escaping decorator class for "getter" objects.
 *
 * @see        Escaper
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Mike Squire <mike@somosis.co.uk>
 */
abstract class GetterDecorator extends Escaper
{
    /**
     * Returns the raw, unescaped value associated with the key supplied.
     *
     * The key might be an index into an array or a value to be passed to the
     * decorated object's get() method.
     *
     * @param  string $key  The key to retrieve
     *
     * @return mixed The value
     */
    public abstract function getRaw($key);

    /**
     * Returns the escaped value associated with the key supplied.
     *
     * Typically (using this implementation) the raw value is obtained using the
     * {@link getRaw()} method, escaped and the result returned.
     *
     * @param  string $key     The key to retrieve
     * @param  string $escaper The escaping method (a PHP function) to use
     *
     * @return mixed The escaped value
     */
    public function get($key, $escaper = null)
    {
        if (!$escaper) {
            $escaper = $this->escaper;
        }

        return Escaper::escape($escaper, $this->getRaw($key));
    }
}
