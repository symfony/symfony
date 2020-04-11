<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Csrf\Exception;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TokenNotFoundException extends \RuntimeException
{
}
class_alias(TokenNotFoundException::class, \Symfony\Component\Security\Csrf\Exception\TokenNotFoundException::class);
