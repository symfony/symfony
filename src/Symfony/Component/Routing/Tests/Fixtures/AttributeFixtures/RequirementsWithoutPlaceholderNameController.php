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

use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/', requirements: ['foo', '\d+'])]
class RequirementsWithoutPlaceholderNameController
{
    #[Route(path: '/{foo}', name: 'foo', requirements: ['foo', '\d+'])]
    public function foo()
    {
    }
}
