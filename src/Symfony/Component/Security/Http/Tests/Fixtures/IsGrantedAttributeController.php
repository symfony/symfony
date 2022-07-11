<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Fixtures;

use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(attributes: ['ROLE_ADMIN', 'ROLE_USER'])]
class IsGrantedAttributeController
{
    #[IsGranted(attributes: ['ROLE_ADMIN'])]
    public function foo()
    {
    }

    public function bar()
    {
    }
}
