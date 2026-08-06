<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\SourceCarriesMetadata;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: UnrelatedLeadView::class)]
class Lead
{
    public int $id = 1;
    public Type $type;

    public function __construct()
    {
        $this->type = new Type();
    }
}
