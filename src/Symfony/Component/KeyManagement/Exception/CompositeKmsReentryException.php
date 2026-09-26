<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Exception;

/**
 * Identifies a recursive call so it cannot be treated as a failed member read.
 *
 * @internal
 */
final class CompositeKmsReentryException extends LogicException
{
    public function __construct()
    {
        parent::__construct('A composite KMS client cannot be re-entered while one of its operations is running.');
    }
}
