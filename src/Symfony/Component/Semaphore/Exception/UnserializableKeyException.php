<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Semaphore\Exception;

/**
 * Thrown when a {@see \Symfony\Component\Semaphore\Key} that has been flagged
 * as unserializable (typically by a store that attached non-portable
 * per-process state, such as live lock instances) is passed to PHP's
 * `serialize()` or to a normalizer.
 *
 * @author Paul Clegg <hello@clegginabox.co.uk>
 */
class UnserializableKeyException extends \RuntimeException implements ExceptionInterface
{
}
