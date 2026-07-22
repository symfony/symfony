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

class MultiParameterAdderDummy extends MultiParameterAdderParentDummy
{
    public ?MultiParameterAdderValue $link = null;
}

class MultiParameterAdderParentDummy
{
    public function addLink(\stdClass $request, MultiParameterAdderValue $link): void
    {
    }
}

class MultiParameterAdderValue
{
}
