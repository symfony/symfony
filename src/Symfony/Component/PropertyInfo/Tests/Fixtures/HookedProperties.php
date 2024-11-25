<?php

/*
  * This file is part of the Symfony package.
  *
  * (c) Fabien Potencier <fabien@symfony.com>
  *
  * For the full copyright and license information, please view the LICENSE
  * file that was distributed with this source code.
  */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

class HookedProperties
{
    public bool $virtualNoSetHook { get => true; }
    public bool $virtualSetHookOnly { set => $value; }
    public bool $virtualHook { get => true; set => $value; }

    public bool $backedNoSetHook = true { get => !$this->backedNoSetHook; }
    public bool $backedSetHookOnly = true { set => $this->backedSetHookOnly = $value; }
    public bool $backedHook = true { get => !$this->backedHook; set => $this->backedHook = $value; }
}
