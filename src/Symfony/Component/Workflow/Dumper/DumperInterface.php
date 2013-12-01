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

/**
 * DumperInterface is the interface implemented by workflow dumper classes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface DumperInterface
{
    /**
     * Dumps a workflow definition.
     *
     * @param Definition $definition A Definition instance
     * @param array      $options    An array of options
     *
     * @return string The representation of the workflow
     */
    public function dump(Definition $definition, array $options = array());
}
