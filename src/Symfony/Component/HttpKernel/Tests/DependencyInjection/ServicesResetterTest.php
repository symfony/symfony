<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServicesResetter as BaseServicesResetter;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;

#[Group('legacy')]
#[IgnoreDeprecations]
class ServicesResetterTest extends TestCase
{
    public function testDeprecatedClassExtendsNewClass()
    {
        $this->assertTrue(is_subclass_of(ServicesResetter::class, BaseServicesResetter::class));
    }
}
