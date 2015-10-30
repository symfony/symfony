<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

/**
 * Represents an environment variable value.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class EnvVariable
{
    private $id;

    /**
     * Constructor.
     *
     * @param string $id The environment variable name
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

    /**
     * __toString.
     *
     * @return string The environment variable name
     */
    public function __toString()
    {
        return (string) $this->id;
    }
}
