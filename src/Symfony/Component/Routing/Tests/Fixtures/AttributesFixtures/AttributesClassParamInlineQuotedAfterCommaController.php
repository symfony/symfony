<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributesFixtures;

use Symfony\Component\Routing\Tests\Fixtures\Attributes\FooAttributes;
use Symfony\Component\Security\Core\User\User;

#[FooAttributes(foo: ['bar' => ['foo','bar'],'foo'],class: 'Symfony\Component\Security\Core\User\User')]
class AttributesClassParamInlineQuotedAfterCommaController
{

}
