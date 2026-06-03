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

use Symfony\Component\Routing\Attribute\DeprecatedAlias;
use Symfony\Component\Routing\Attribute\Route;

class DeprecatedAliasCustomMessageRouteController
{

    #[Route('/path', name: 'action_with_deprecated_alias', alias: new DeprecatedAlias('my_other_alias_deprecated', 'MyBundleFixture', '1.0', message: '%alias_id% alias is deprecated.'))]
    public function action()
    {
    }
}
