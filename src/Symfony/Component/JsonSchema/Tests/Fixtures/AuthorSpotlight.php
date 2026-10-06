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

class AuthorSpotlight
{
    /**
     * The highlighted author.
     */
    private Author $author;

    public function getAuthor(): Author
    {
        return $this->author;
    }
}
