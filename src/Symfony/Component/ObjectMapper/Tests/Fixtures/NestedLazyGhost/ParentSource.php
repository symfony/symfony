<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\NestedLazyGhost;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: ParentTarget::class)]
class ParentSource
{
    public ArrayObjectSource $arrayObject;
    public StdClassSource $stdClass;

    public function __construct()
    {
        $this->arrayObject = new ArrayObjectSource();
        $this->stdClass = new StdClassSource();
    }
}
