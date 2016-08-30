<?php

namespace Symfony\Component\HttpKernel\Tests\Fixtures\Controller;

class NullableController
{
    public function action(? string $foo, ? \stdClass $bar, ? string $baz = 'value', $mandatory)
    {
    }
}
