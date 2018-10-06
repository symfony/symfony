<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Messenger;

use Symfony\Bridge\Doctrine\ManagerRegistry;

/**
 * Create a Doctrine ORM transaction middleware to be used in a message bus from an entity manager name.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 *
 * @final
 */
class DoctrineTransactionMiddlewareFactory
{
    private $managerRegistry;

    public function __construct(ManagerRegistry $managerRegistry)
    {
        $this->managerRegistry = $managerRegistry;
    }

    public function createMiddleware(string $managerName): DoctrineTransactionMiddleware
    {
        return new DoctrineTransactionMiddleware($this->managerRegistry, $managerName);
    }
}
