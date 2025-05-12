<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Uid\Exception;

class InvalidUuidException extends InvalidArgumentException
{
    public function __construct(
        public readonly int $type,
        string $value,
    ) {
        parent::__construct(\sprintf('Invalid UUID%s: "%s".', $type ? 'v'.$type : '', $value));
    }
}
