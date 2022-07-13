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
 * Keeps the state of lazy ghost objects.
 *
 * As a micro-optimization, this class uses no type declarations.
 *
 * @internal
 */
class GhostObjectId
{
    public int $id;

    public function __construct()
    {
        $this->id = spl_object_id($this);
    }

    public function __clone()
    {
        $this->id = spl_object_id($this);
    }

    public function __destruct()
    {
        unset(GhostObjectRegistry::$states[$this->id]);
    }
}
