<?php

namespace Symfony\Component\Serializer\Tests\Fixtures;

enum IntegerBackedEnumDummy: int
{
    case SUCCESS = 200;
    case NOT_FOUND = 404;
}
