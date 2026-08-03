<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

use Symfony\Component\JsonStreamer\Attribute\StreamedName;

class DummyWithSpecialCharacterNamedProperties
{
    #[StreamedName('https://symfony.com/école')]
    public int $url = 1;

    #[StreamedName("line\u{2028}separator")]
    public int $lineSeparator = 2;
}
