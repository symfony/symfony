<?php

namespace Symfony\Component\Security\Acl\Model;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * This class represents an individual entry in the ACL list.
 *
 * Instances MUST be immutable, as they are returned by the ACL and should not
 * allow client modification.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface EntryInterface extends \Serializable
{
    /**
     * The ACL this ACE is associated with.
     *
     * @return AclInterface
     */
    function getAcl();

    /**
     * The primary key of this ACE
     *
     * @return integer
     */
    function getId();

    /**
     * The permission mask of this ACE
     *
     * @return integer
     */
    function getMask();

    /**
     * The security identity associated with this ACE
     *
     * @return SecurityIdentityInterface
     */
    function getSecurityIdentity();

    /**
     * The strategy for comparing masks
     *
     * @return string
     */
    function getStrategy();

    /**
     * Returns whether this ACE is granting, or denying
     *
     * @return Boolean
     */
    function isGranting();
}