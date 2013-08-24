<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Role;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * SwitchUserRole is used when the current user temporarily impersonates
 * another one.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @since v2.0.0
 */
class SwitchUserRole extends Role
{
    private $source;

    /**
     * Constructor.
     *
     * @param string         $role   The role as a string
     * @param TokenInterface $source The original token
     *
     * @since v2.0.0
     */
    public function __construct($role, TokenInterface $source)
    {
        parent::__construct($role);

        $this->source = $source;
    }

    /**
     * Returns the original Token.
     *
     * @return TokenInterface The original TokenInterface instance
     *
     * @since v2.0.0
     */
    public function getSource()
    {
        return $this->source;
    }
}
