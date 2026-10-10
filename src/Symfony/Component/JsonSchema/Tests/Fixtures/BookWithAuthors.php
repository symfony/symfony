<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests\Fixtures;

class BookWithAuthors
{
    public string $title;
    public Author $author;
    public ?Author $coAuthor = null;
    /** @var list<Author> */
    public array $reviewers = [];
    /** @var array<string, Author> */
    public array $authorsByRole = [];
    /** @var list<string> */
    public array $tags = [];
}
