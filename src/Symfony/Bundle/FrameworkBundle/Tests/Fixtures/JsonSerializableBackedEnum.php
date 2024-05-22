<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures;

use JsonSerializable;

enum JsonSerializableBackedEnum: string implements JsonSerializable
{
    case Get = 'GET';

    public function jsonSerialize(): string {
        return 'custom_get_string';
    }
}
