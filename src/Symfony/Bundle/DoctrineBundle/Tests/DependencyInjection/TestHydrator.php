<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineBundle\Tests\DependencyInjection;

class TestHydrator extends \Doctrine\ORM\Internal\Hydration\AbstractHydrator
{
    protected function _hydrateAll();
    {
        return array();
    }
}
