<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Tests\Fixtures\Attribute\CustomAnyAttribute;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Attribute\CustomMethodAttribute;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Attribute\CustomPropertyAttribute;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Attribute\CustomParameterAttribute;

#[CustomAnyAttribute]
final class TaggedService4
{
    #[CustomAnyAttribute]
    #[CustomPropertyAttribute(someAttribute: "on name")]
    public string $name;

    public function __construct(
        #[CustomAnyAttribute]
        #[CustomParameterAttribute(someAttribute: "on param1 in constructor")]
        private string $param1,
        #[CustomAnyAttribute]
        #[CustomParameterAttribute(someAttribute: "on param2 in constructor")]
        string $param2
    ) {}

    #[CustomAnyAttribute]
    #[CustomMethodAttribute(someAttribute: "on fooAction")]
    public function fooAction(
        #[CustomAnyAttribute]
        #[CustomParameterAttribute(someAttribute: "on param1 in fooAction")]
        string $param1
    ) {}

    #[CustomAnyAttribute]
    #[CustomMethodAttribute(someAttribute: "on barAction")]
    public function barAction() {}

    public function someOtherMethod() {}
}
