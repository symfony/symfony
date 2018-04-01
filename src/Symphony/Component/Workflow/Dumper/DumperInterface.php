<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Workflow\Dumper;

use Symphony\Component\Workflow\Definition;
use Symphony\Component\Workflow\Marking;

/**
 * DumperInterface is the interface implemented by workflow dumper classes.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
interface DumperInterface
{
    /**
     * Dumps a workflow definition.
     *
     * @param Definition   $definition A Definition instance
     * @param Marking|null $marking    A Marking instance
     * @param array        $options    An array of options
     *
     * @return string The representation of the workflow
     */
    public function dump(Definition $definition, Marking $marking = null, array $options = array());
}
