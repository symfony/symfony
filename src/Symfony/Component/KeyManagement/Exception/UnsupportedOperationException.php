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
 * Thrown when the underlying KMS does not support a requested operation.
 *
 * Additional authenticated data, key rotation and asymmetric signing are
 * examples of what a backend may lack.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
class UnsupportedOperationException extends RuntimeException
{
}
