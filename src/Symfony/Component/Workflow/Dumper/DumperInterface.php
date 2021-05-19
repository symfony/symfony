<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Dumper;

use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Marking;

/**
 * DumperInterface is the interface implemented by workflow dumper classes.
 */
interface DumperInterface
{
    /**
     * Dumps a workflow definition.
     *
     * @return string The representation of the workflow
     */
    public function dump(Definition $definition, Marking $marking = null, array $options = []);
}
