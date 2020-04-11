<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Csrf\TokenGenerator;

/**
 * Generates CSRF tokens.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface TokenGeneratorInterface
{
    /**
     * Generates a CSRF token.
     *
     * @return string The generated CSRF token
     */
    public function generateToken();
}
class_alias(TokenGeneratorInterface::class, \Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface::class);
