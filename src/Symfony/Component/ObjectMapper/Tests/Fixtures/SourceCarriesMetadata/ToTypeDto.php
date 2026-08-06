<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\SourceCarriesMetadata;

final class ToTypeDto
{
    public static function transform(mixed $value, object $source, ?object $target): TypeDto
    {
        return new TypeDto($value->id, $value->name);
    }
}
