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

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedPath;

class GroupedPathArticle
{
    #[Groups(['article:read', 'article:list'])]
    #[SerializedPath('[meta][title]', groups: ['article:read'])]
    public string $title = '';
}
