<?php

namespace Symfony\Component\Security\Role;

use Symfony\Component\Security\Authentication\Token\TokenInterface;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * SwitchUserRole is used when the current user temporarily impersonates
 * another one.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class SwitchUserRole extends Role
{
    protected $source;

    /**
     * Constructor.
     *
     * @param string         $role   The role as a string
     * @param TokenInterface $source The original token
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
     */
    public function getSource()
    {
        return $this->source;
    }
}
