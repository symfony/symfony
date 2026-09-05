<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Exception;

/**
 * Represents an argument received while the input definition expects no more of them.
 *
 * @author Daniel Leech <daniel@dantleech.com>
 */
class UnexpectedArgumentException extends RuntimeException implements ExceptionInterface
{
}
