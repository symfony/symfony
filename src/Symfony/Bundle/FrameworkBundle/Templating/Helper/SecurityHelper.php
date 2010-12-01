<?php

namespace Symfony\Bundle\FrameworkBundle\Templating\Helper;

use Symfony\Component\Templating\Helper\Helper;
use Symfony\Component\Security\SecurityContext;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

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

    public function vote($role, $object = null)
    {
        if (null === $this->context) {
            return false;
        }

        return $this->context->vote($role, $object);
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
