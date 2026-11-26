<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Paint;

use Symfony\Component\HtmlRenderer\Resource\ImageResource;

/** @internal */
final class BorderImageSource
{
    /** @param ImageResource|array<string, mixed> $source Image bytes or resolved gradient geometry */
    public function __construct(public readonly ImageResource|array $source)
    {
    }
}
