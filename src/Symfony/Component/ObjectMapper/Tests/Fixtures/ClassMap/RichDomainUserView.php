<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap;

use Symfony\Component\ObjectMapper\Attribute\Map;

final class RichDomainUserView
{
    #[Map(source: 'id')]
    public int $id;

    #[Map(source: 'username')]
    public string $username;
}
