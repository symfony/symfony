<?php

namespace Symfony\Component\DependencyInjection\Dumper;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * DumperInterface is the interface implemented by service container dumper classes.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface DumperInterface
{
    /**
     * Dumps the service container.
     *
     * @param  array  $options An array of options
     *
     * @return string The representation of the service container
     */
    function dump(array $options = array());
}
