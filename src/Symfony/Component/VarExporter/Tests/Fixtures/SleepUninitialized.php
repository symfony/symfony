<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter\Tests\Fixtures;

abstract class SleepUninitializedBase
{
    public string $set;
    public string $unsetOnParent;

    public function __sleep(): array
    {
        return ['set', 'unsetOnParent'];
    }
}

class SleepUninitialized extends SleepUninitializedBase
{
    public function __construct(string $value)
    {
        $this->set = $value;
    }
}
