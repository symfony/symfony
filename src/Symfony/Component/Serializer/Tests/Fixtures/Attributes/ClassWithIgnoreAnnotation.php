<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures\Attributes;

use Symfony\Component\Serializer\Annotation\Ignore;

class ClassWithIgnoreAnnotation
{
    public string $foo;

    #[Ignore]
    public function isSomeIgnoredMethod(): bool
    {
        return true;
    }
}
