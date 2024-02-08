<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Attribute\Route;

#[
    Route(path: '/1', name: 'route1', schemes: ['https'], methods: ['GET']),
    Route(path: '/2', name: 'route2', schemes: ['https'], methods: ['GET']),
]
class BazClass
{
    public function __invoke()
    {
    }
}
