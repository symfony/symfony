<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Generator\Dumper;

/**
 * GeneratorDumperInterface is the interface that all generator dumper classes must implement.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface GeneratorDumperInterface
{
    /**
     * Dumps a set of routes to a PHP class.
     *
     * Available options:
     *
     *  * class:      The class name
     *  * base_class: The base class name
     *
     * @param  array  $options An array of options
     *
     * @return string A PHP class representing the generator class
     */
    function dump(array $options = array());
}
