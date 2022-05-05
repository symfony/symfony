<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class ClearObjectManagers implements ResetInterface
{
    public function __construct(private ManagerRegistry $managerRegistry)
    {
    }

    public function reset(): void
    {
        foreach ($this->managerRegistry->getManagers() as $manager) {
            $manager->clear();
        }
    }
}
