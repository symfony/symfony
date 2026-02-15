<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Loader\Configurator\Traits;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Loader\Configurator\Traits\PrefixTrait;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class PrefixTraitTest extends TestCase
{
    public function testAddLocalizedPrefixUpdatesAliases()
    {
        $collection = new RouteCollection();
        $collection->add('app_route', new Route('/path'));
        $collection->addAlias('app_alias', 'app_route');

        $trait = new class {
            use PrefixTrait;

            public function add(RouteCollection $c, array $p)
            {
                $this->addPrefix($c, $p, false);
            }
        };

        $trait->add($collection, ['en' => '/en', 'fr' => '/fr']);

        $this->assertNull($collection->get('app_route'));

        $this->assertNotNull($collection->get('app_route.en'));
        $this->assertNotNull($collection->get('app_route.fr'));

        $this->assertNull($collection->getAlias('app_alias'), 'The original alias should be removed as its target no longer exists');

        $aliasEn = $collection->getAlias('app_alias.en');
        $this->assertNotNull($aliasEn, 'Localized alias for EN should exist');
        $this->assertEquals('app_route.en', $aliasEn->getId());

        $aliasFr = $collection->getAlias('app_alias.fr');
        $this->assertNotNull($aliasFr, 'Localized alias for FR should exist');
        $this->assertEquals('app_route.fr', $aliasFr->getId());
    }
}
