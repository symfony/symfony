<?php

namespace Symfony\Component\Serializer\Tests\Fixtures\Annotations;

use Symfony\Component\Serializer\Annotation\Ignore;

class Entity45016
{
    /**
     * @var int
     */
    private $id = 1234;

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @Ignore()
     */
    public function badIgnore(): bool
    {
        return true;
    }
}
