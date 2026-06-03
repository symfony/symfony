<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Fixtures;

class CascadingEntityUnion
{
    public CascadedChild|\stdClass $classes;
    public CascadedChild|array $classAndArray;
    public CascadedChild|null $classAndNull;
    public array|null $arrayAndNull;
    public CascadedChild|array|null $classAndArrayAndNull;
    public int|string $scalars;
    public int|null $scalarAndNull;
    public CascadedChild|int $classAndScalar;
    public array|int $arrayAndScalar;
}
