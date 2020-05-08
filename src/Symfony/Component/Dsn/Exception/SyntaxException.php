<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Dsn\Exception;

/**
 * Syntax of the DSN string is invalid.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class SyntaxException extends InvalidDsnException
{
}
