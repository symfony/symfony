<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Fixtures\ReturnedMessages;

class Article
{
    public function __construct(
        public ArticleStatus $status,
    ) {
    }

    public static function create(): static
    {
        return new static(ArticleStatus::Draft);
    }

    public function getStatus(): ?ArticleStatus
    {
        return $this->status;
    }

    public function getTitle(): string
    {
        $title = ArticleStatus::Draft === $this->status ? 'method-return-variable-draft' : 'method-return-variable-published';

        return $title;
    }

    public function getSummary(): string
    {
        return $this->getTitle();
    }

    public function getRecursive(bool $stop): string
    {
        return $stop ? 'method-return-recursive' : $this->getRecursive(true);
    }
}
