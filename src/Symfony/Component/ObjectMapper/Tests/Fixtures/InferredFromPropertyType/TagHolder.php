<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\InferredFromPropertyType;

class TagHolder
{
    public function __construct(
        public ?Tag $tag = new Tag(),
    ) {
    }
}
