<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Passport;

/**
 * Represents a passport which contains attributes.
 */
interface AttributedPassportInterface extends PassportInterface
{
    /**
     * @param mixed $value
     */
    public function setAttribute(string $name, $value): void;

    /**
     * @param mixed $default
     *
     * @return mixed
     */
    public function getAttribute(string $name, $default = null);
}
