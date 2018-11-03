<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter\Internal;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class Reference
{
    public $id;
    public $value;
    public $count = 0;

    public function __construct(int $id, $value = null)
    {
        $this->id = $id;
        $this->value = $value;
    }
}
