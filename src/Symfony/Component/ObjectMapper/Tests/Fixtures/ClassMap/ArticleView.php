<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(source: Article::class)]
final class ArticleView
{
    #[Map(source: 'title', transform: 'strrev')]
    public ?string $headline = null;

    #[Map(source: 'address.city')]
    public ?string $city = null;

    #[Map(source: 'note')]
    public ?string $note = null;

    public ?string $slug = null;

    #[Map(source: 'name', transform: 'strtoupper')]
    public ?string $name = null;

    #[Map(source: 'body')]
    public ?string $excerpt = null;
}
