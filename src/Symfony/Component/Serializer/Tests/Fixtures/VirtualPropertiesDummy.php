<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures;

class VirtualPropertiesDummy
{
    public string $name = 'Alice';

    public string $readOnly {
        get => strtoupper($this->name);
    }

    public string $writeOnly {
        set (string $value) {
            $this->name = $value;
        }
    }

    public $untypedNull {
        get => null;
    }

    private $privateUntyped {
        get => 'secret';
    }
}
