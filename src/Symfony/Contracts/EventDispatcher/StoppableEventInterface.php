<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\EventDispatcher;

use Psr\EventDispatcher\StoppableEventInterface as PsrStoppableEventInterface;

interface StoppableEventInterface extends PsrStoppableEventInterface
{
    public function stopPropagation(): void;
}
