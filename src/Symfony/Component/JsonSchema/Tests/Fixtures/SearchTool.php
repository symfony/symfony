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

use Symfony\Component\JsonSchema\Attribute\JsonSchemaConstraint;

class SearchTool
{
    /**
     * Searches the catalog.
     *
     * @param string       $query The text
     *                            to search for
     * @param list<string> $tags  Only the products with these tags
     */
    public function __invoke(
        string $query,
        ?Author $author,
        #[JsonSchemaConstraint(minimum: 1, maximum: 100)]
        int $limit = 10,
        array $tags = [],
        Suit $suit = Suit::Hearts,
        string ...$extra,
    ): void {
    }
}
