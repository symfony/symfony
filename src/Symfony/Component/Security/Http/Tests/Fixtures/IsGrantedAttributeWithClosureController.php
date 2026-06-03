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
use Symfony\Component\Security\Http\Attribute\IsGrantedContext;

#[IsGranted(static function (IsGrantedContext $context) {
    return $context->isGranted('ROLE_USER');
})]
class IsGrantedAttributeWithClosureController
{
    #[IsGranted(static function (IsGrantedContext $context) {
        return $context->isGranted('ROLE_ADMIN');
    })]
    public function foo()
    {
    }

    public function bar()
    {
    }
}
