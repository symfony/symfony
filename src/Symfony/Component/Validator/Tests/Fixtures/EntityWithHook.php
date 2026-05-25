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

class EntityWithHook
{
    public string $name;

    protected int $id = 1;

    protected string $withHook {
        get {
            $this->withHook ??= strtolower($this->name);

            return $this->withHook;
        }
    }

    protected string $withHookOnSelf {
        get => strtolower($this->withHookOnSelf);
    }
}
