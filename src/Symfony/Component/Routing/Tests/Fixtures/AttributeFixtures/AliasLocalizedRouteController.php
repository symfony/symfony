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

class AliasLocalizedRouteController
{
    #[Route(['nl_NL' => '/nl/localized', 'fr_FR' => '/fr/localized'], name: 'localized_route', alias: ['localized_alias'])]
    public function localized()
    {
    }
}
