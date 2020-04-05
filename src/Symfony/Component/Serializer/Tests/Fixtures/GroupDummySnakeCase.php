<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
