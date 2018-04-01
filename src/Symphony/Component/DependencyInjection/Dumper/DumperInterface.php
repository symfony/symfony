<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Dumper;

/**
 * DumperInterface is the interface implemented by service container dumper classes.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
interface DumperInterface
{
    /**
     * Dumps the service container.
     *
     * @param array $options An array of options
     *
     * @return string The representation of the service container
     */
    public function dump(array $options = array());
}
