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

/**
 * Class Php74Dummy
 *
 * @author Tales Santos <tales.augusto.santos@gmail.com>
 */
class Php74Dummy
{
    public int $int;
    public ?string $string;
    public Dummy $dummy;
    public ?Dummy $optionalDummy;
}
