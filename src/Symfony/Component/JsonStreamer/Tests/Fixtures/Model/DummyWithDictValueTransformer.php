<?php

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

use Symfony\Component\JsonStreamer\Attribute\ValueTransformer;

class DummyWithDictValueTransformer
{
    public static int $calls = 0;

    #[ValueTransformer(nativeToStream: [self::class, 'toDict'])]
    public int $id = 1;

    /**
     * @return array<string, mixed>
     */
    public static function toDict(int $id): array
    {
        ++self::$calls;

        return ['0' => $id];
    }
}
