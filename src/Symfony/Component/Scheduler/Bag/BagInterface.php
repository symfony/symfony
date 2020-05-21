<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bag;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface BagInterface
{
    public function getContent(): array;

    public function getName(): string;
}
