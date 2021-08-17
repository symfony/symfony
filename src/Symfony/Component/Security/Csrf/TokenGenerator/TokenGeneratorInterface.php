<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Csrf\TokenGenerator;

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
     * @return string
     */
    public function generateToken();
}
