<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Fixtures;

use Symfony\Component\Validator\Constraints as Assert;

#[Assert\Cascade]
class WriteOnlyVirtualPropertyEntity
{
    public string $name = 'Alice';

    public ?CascadedChild $child = null;

    public CascadedChild $writeOnlyChild {
        set (CascadedChild $child) {
            $this->child = $child;
        }
    }

    public string $writeOnlyString {
        set (string $value) {
            $this->name = $value;
        }
    }
}
