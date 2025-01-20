<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributesFixtures;

use Symfony\Component\Routing\Tests\Fixtures\Attributes\FooAttributes;

#[FooAttributes(
    class: 'Symfony\Component\Security\Core\User\User',
    foo: [
        'bar' => ['foo','bar'],
        'foo',
    ]
)]
class AttributesClassParamQuotedAfterParenthesisController
{

}
