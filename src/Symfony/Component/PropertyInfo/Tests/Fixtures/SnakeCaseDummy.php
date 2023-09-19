<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

class SnakeCaseDummy
{
    private string $snake_property;
    private string $snake_readOnly;
    private string $snake_method;

    public function getSnakeProperty()
    {
        return $this->snake_property;
    }

    public function getSnakeReadOnly()
    {
        return $this->snake_readOnly;
    }

    public function setSnakeProperty($snake_property)
    {
        $this->snake_property = $snake_property;
    }

    public function setSnake_method($snake_method)
    {
        $this->snake_method = $snake_method;
    }
}
