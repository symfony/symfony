<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\DirectAccess;

class AdderTarget
{
    public array $tags = [];

    public function addTag(string $tag): void
    {
        $this->tags[] = strtoupper($tag);
    }

    public function removeTag(string $tag): void
    {
    }
}
