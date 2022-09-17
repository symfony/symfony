<?php

namespace Symfony\Component\Validator\Tests\Constraints\Fixtures;

use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\When;

#[When(expression: 'true', constraints: [
    new Callback('callback'),
])]
class WhenTestWithAttributes
{
    #[When(expression: 'true', constraints: [
        new NotNull(),
        new NotBlank(),
    ])]
    private $foo;

    #[When(expression: 'false', constraints: [
        new NotNull(),
        new NotBlank(),
    ], groups: ['foo'])]
    private $bar;

    #[When(expression: 'true', constraints: [
        new NotNull(),
        new NotBlank(),
    ])]
    public function getBaz()
    {
        return null;
    }

    public function callback()
    {
    }
}
