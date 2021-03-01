<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributesFixtures;

use Symfony\Component\Routing\Tests\Fixtures\Attributes\FooAttributes;
use Symfony\Component\Security\Core\User\User;

#[FooAttributes(
    class: User::class,
    foo: [
        'bar' => ['foo','bar'],
        'foo'
    ]
)]
class AttributesClassParamAfterParenthesisController
{

}
