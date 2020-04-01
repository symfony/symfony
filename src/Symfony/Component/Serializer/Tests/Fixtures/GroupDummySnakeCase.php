<?php

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @author Laurent Masforné <l.masforne@gmail.com>
 */
class GroupDummySnakeCase
{
    /**
     * @Groups({"name_converter"})
     */
    protected $snake_case;

    public function getSnakeCase()
    {
        return $this->snake_case;
    }

    public function setSnakeCase($snake_case): void
    {
        $this->snake_case = $snake_case;
    }
}
