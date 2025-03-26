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

class AliasRouteController
{
    #[Route('/path', name: 'action_with_alias', alias: ['alias', 'completely_different_name'])]
    public function action()
    {
    }
}
