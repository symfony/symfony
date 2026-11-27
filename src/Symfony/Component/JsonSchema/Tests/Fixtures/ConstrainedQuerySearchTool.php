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

class ConstrainedQuerySearchTool
{
    #[JsonSchemaConstraint(minLength: 3, required: false)]
    public string $query = '';

    public function __invoke(string $query): void
    {
    }
}
