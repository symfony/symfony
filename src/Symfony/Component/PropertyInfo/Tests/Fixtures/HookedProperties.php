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
    public string $hookGetOnly {
        get => $this->hookGetOnly . ' (hooked on get)';
    }
    public string $hookSetOnly {
        set(string $value) {
            $this->hookSetOnly = $value . ' (hooked on set)';
        }
    }
    public string $hookBoth {
        get => $this->hookBoth . ' (hooked on get)';
        set(string $value) {
            $this->hookBoth = $value . ' (hooked on set)';
        }
    }
}
