<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\SubclassTargetWithTransform;

class ChildTarget extends ParentTarget
{
    public static function transform(mixed $value, object $source, ?object $target): self
    {
        return new self();
    }
}
