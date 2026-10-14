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

class DocumentedArticle
{
    /**
     * The article headline.
     */
    public string $headline;

    #[JsonSchemaConstraint(title: 'Slug', description: 'URL fragment.', pattern: '^[a-z-]+$', minLength: 1, maxLength: 64, example: 'hello-world', deprecated: true)]
    public string $slug;

    #[JsonSchemaConstraint(minimum: 0, exclusiveMaximum: 10, multipleOf: 0.5)]
    public float $rating;

    #[JsonSchemaConstraint(format: 'email', readOnly: true)]
    public string $contact;

    /** @var list<string> */
    #[JsonSchemaConstraint(minItems: 1, uniqueItems: true)]
    public array $keywords;

    #[JsonSchemaConstraint(exclusiveMinimum: 0)]
    public int $views;

    #[JsonSchemaConstraint(const: 'article', required: true)]
    public string $kind;
}
