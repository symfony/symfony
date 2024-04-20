<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Csrf;

/**
 * A CSRF token.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CsrfToken
{
    private string $id;
    private string $value;

    public function __construct(string $id, #[\SensitiveParameter] ?string $value)
    {
        $this->id = $id;
        $this->value = $value ?? '';
    }

    /**
     * Returns the ID of the CSRF token.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Returns the value of the CSRF token.
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Returns the value of the CSRF token.
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
