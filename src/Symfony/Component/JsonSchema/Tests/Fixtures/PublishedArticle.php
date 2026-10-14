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

use Symfony\Component\Serializer\Attribute\SerializedPath;

class PublishedArticle
{
    public string $body = '';

    #[SerializedPath('[meta][author][name]')]
    public string $authorName = '';

    public function __construct(
        #[SerializedPath('[meta][title]')]
        public string $title,
    ) {
    }
}
