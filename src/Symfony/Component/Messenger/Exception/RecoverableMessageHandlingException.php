<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Exception;

/**
 * A concrete implementation of RecoverableExceptionInterface that can be used directly.
 *
 * @author Frederic Bouchery <frederic@bouchery.fr>
 */
class RecoverableMessageHandlingException extends RuntimeException implements RecoverableExceptionInterface
{
}
