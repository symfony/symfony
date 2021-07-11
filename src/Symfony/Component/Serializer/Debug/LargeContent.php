<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Debug;

final class LargeContent
{
    public const LIMIT_BYTES = 1024 * 1024;
    public $message;

    public function __construct()
    {
        $this->message = sprintf(
            'The content of the serialized/deserialized data exceeded %d kB.',
            self::LIMIT_BYTES / 1024
        );
    }

    public function __toString(): string
    {
        return $this->message;
    }
}
