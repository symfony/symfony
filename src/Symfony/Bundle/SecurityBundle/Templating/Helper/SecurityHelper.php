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
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * SecurityHelper provides read-only access to the security checker.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SecurityHelper extends Helper
{
    private $securityChecker;

    /**
     * @param SecurityContextInterface|AuthorizationCheckerInterface
     *
     * Passing a SecurityContextInterface as a first argument was deprecated in 2.7 and will be removed in 3.0
     */
    public function __construct($securityChecker = null)
    {
        $this->securityChecker = $securityChecker;
    }

    public function isGranted($role, $object = null, $field = null)
    {
        if (null === $this->securityChecker) {
            return false;
        }

        if (null !== $field) {
            $object = new FieldVote($object, $field);
        }

        return $this->securityChecker->isGranted($role, $object);
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
