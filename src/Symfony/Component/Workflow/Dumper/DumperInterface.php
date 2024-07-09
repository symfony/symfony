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
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
interface DumperInterface
{
    /**
     * Dumps a workflow definition.
     */
    public function dump(Definition $definition, ?Marking $marking = null, array $options = []): string;
}
