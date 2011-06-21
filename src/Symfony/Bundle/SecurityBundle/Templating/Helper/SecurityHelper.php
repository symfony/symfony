<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Templating\Helper;

use Symfony\Component\Security\Acl\Voter\FieldVote;
use Symfony\Component\Templating\Helper\Helper;
use Symfony\Component\Security\Core\SecurityContextInterface;

/**
 * SecurityHelper provides read-only access to the security context.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SecurityHelper extends Helper
{
    private $context;

    /**
     * Constructor.
     *
     * @param SecurityContextInterface $context A SecurityContext instance
     */
    public function __construct(SecurityContextInterface $context = null)
    {
        $this->context = $context;
    }

    public function isGranted($role, $object = null, $field = null)
    {
        if (null === $this->context) {
            return false;
        }

        if (null !== $field) {
            $object = new FieldVote($object, $field);
        }

        return $this->context->isGranted($role, $object);
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
