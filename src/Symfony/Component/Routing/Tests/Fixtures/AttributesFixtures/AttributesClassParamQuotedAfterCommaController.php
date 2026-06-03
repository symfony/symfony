<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributesFixtures;

use Symfony\Component\Routing\Tests\Fixtures\Attributes\FooAttributes;

#[FooAttributes(
    foo: [
        'bar' => ['foo','bar'],
        'foo',
    ],
    class: 'Symfony\Component\Security\Core\User\User'
)]
class AttributesClassParamQuotedAfterCommaController
{

}
