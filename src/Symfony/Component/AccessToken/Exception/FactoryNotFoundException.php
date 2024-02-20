<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare (strict_types=1);

namespace Symfony\Component\AccessToken\Exception;

/**
 * Credentials factory not found for a user-provided URI scheme.
 *
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
class FactoryNotFoundException extends RuntimeException
{
}
