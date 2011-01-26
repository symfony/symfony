<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Templating\Helper;

use Symfony\Component\Templating\Helper\Helper;
use Symfony\Component\Security\Core\SecurityContext;

/**
 * SecurityHelper provides read-only access to the security context.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class SecurityHelper extends Helper
{
    protected $context;

    /**
     * Constructor.
     *
     * @param SecurityContext $context A SecurityContext instance
     */
    public function __construct(SecurityContext $context = null)
    {
        $this->context = $context;
    }

    public function vote($role, $object = null, $field = null)
    {
        if (null === $this->context) {
            return false;
        }

        return $this->context->vote($role, $object, $field);
    }

    /**
     * Returns the canonical name of this helper.
     *
     * @return string The canonical name
     */
    public function getName()
    {
        return 'security';
    }
}
