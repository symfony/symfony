<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Handler;

interface BatchStrategyInterface
{
    public function shouldHandle(object $lastMessage): bool;

    public function beforeHandle(): void;

    public function afterHandle(): void;
}
