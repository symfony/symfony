<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributesFixtures;

use Symfony\Component\Routing\Tests\Fixtures\Attributes\FooAttributes;

#[FooAttributes(class: \stdClass::class,foo: ['bar' => ['foo','bar'],'foo'])]
class AttributesClassParamInlineAfterParenthesisController
{

}
