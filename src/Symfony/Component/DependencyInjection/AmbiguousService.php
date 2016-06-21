<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

use Symfony\Component\DependencyInjection\Exception\AmbiguousServiceException;

class AmbiguousService
{
    static public function throwException($class, $services)
    {
        throw new AmbiguousServiceException($class, $services);
    }
}
