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
 * @deprecated since version 4.3, to be removed in 5.0. Use strings as roles instead.
 */
class SwitchUserRole extends Role
{
    private $deprecationTriggered = false;
    private $source;

    /**
     * @param string $role The role as a string
     */
    public function __construct(string $role, TokenInterface $source)
    {
        if ($triggerDeprecation = \func_num_args() < 3 || func_get_arg(2)) {
            @trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.3 and will be removed in 5.0. Use strings as roles instead.', __CLASS__), \E_USER_DEPRECATED);

            $this->deprecationTriggered = true;
        }

        parent::__construct($role, $triggerDeprecation);

        $this->source = $source;
    }

    /**
     * Returns the original Token.
     *
     * @return TokenInterface The original TokenInterface instance
     */
    public function getSource()
    {
        if (!$this->deprecationTriggered && (\func_num_args() < 1 || func_get_arg(0))) {
            @trigger_error(sprintf('The "%s" class is deprecated since version 4.3 and will be removed in 5.0. Use strings as roles instead.', __CLASS__), \E_USER_DEPRECATED);

            $this->deprecationTriggered = true;
        }

        return $this->source;
    }
}
