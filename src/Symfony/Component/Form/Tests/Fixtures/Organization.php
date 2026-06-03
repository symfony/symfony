<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures;

class Organization
{
    public $authors = [];

    public function __construct(array $authors = [])
    {
        $this->authors = $authors;
    }

    public function getAuthors(): array
    {
        return $this->authors;
    }

    public function addAuthor(Author $author): self
    {
        $this->authors[] = $author;

        return $this;
    }

    public function removeAuthor(Author $author): self
    {
        if (false !== $key = array_search($author, $this->authors, true)) {
            array_splice($this->authors, $key, 1);
        }

        return $this;
    }
}
