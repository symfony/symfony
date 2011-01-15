<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

class Alias
{
    protected $id;
    protected $public;

    public function __construct($id, $public = true)
    {
        $this->id = strtolower($id);
        $this->public = $public;
    }

    public function isPublic()
    {
        return $this->public;
    }

    public function setPublic($boolean)
    {
        $this->public = (Boolean) $boolean;
    }

    public function __toString()
    {
        return $this->id;
    }
}