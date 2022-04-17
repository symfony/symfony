<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Security;

use Psr\Container\ContainerInterface;
use Symfony\Component\Security\Core\Security as LegacySecurity;

/**
 * Helper class for commonly-needed security tasks.
 *
 * @final
 */
class Security extends LegacySecurity
{
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container, false);
    }
}
