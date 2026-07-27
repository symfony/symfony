<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\MapExistingObject;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: Post::class)]
final class PostDto
{
    #[Map]
    public string $title = 'a post';

    #[Map(transform: NestedExistingTagTransformer::class)]
    public TagDto $tag;

    public function __construct()
    {
        $this->tag = new TagDto();
    }
}
