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

class MultipleDeprecatedAliasRouteController
{
    #[Route('/path', name: 'action_with_multiple_deprecated_alias', alias: [
        new DeprecatedAlias('my_first_alias_deprecated', 'MyFirstBundleFixture', '1.0'),
        new DeprecatedAlias('my_second_alias_deprecated', 'MySecondBundleFixture', '2.0'),
        new DeprecatedAlias('my_third_alias_deprecated', 'SurprisedThirdBundleFixture', '3.0'),
    ])]
    public function action()
    {
    }
}
