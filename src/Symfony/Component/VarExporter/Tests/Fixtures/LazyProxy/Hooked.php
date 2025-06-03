<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy;

class Hooked
{
    public int $notBacked {
        get { return 123; }
        set { throw \LogicException('Cannot set value.'); }
    }

    public int $backed {
        get { return $this->backed ??= 234; }
        set { $this->backed = $value; }
    }
}
