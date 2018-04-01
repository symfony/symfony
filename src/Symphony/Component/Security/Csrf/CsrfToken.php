<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Csrf;

/**
 * A CSRF token.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CsrfToken
{
    private $id;
    private $value;

    public function __construct(string $id, ?string $value)
    {
        $this->id = $id;
        $this->value = $value ?? '';
    }

    /**
     * Returns the ID of the CSRF token.
     *
     * @return string The token ID
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Returns the value of the CSRF token.
     *
     * @return string The token value
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Returns the value of the CSRF token.
     *
     * @return string The token value
     */
    public function __toString()
    {
        return $this->value;
    }
}
