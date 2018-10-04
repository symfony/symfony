<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Exception;

/**
 * NotExpirableStoreException is thrown when a store doesn't support expiration of locks.
 *
 * @author Ganesh Chandrasekaran <gchandrasekaran@wayfair.com>
 */
class NotExpirableStoreException extends \LogicException implements ExceptionInterface
{
}
