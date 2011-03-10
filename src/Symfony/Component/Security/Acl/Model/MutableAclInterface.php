<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Acl\Model;

use Doctrine\Common\NotifyPropertyChanged;

/**
 * This interface adds mutators for the AclInterface.
 *
 * All changes to Access Control Entries must go through this interface. Access
 * Control Entries must never be modified directly.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface MutableAclInterface extends AclInterface, NotifyPropertyChanged
{
    /**
     * Deletes a class-based ACE
     *
     * @param integer $index
     * @return void
     */
    function deleteClassAce($index);

    /**
     * Deletes a class-field-based ACE
     *
     * @param integer $index
     * @param string $field
     * @return void
     */
    function deleteClassFieldAce($index, $field);

    /**
     * Deletes an object-based ACE
     *
     * @param integer $index
     * @return void
     */
    function deleteObjectAce($index);

    /**
     * Deletes an object-field-based ACE
     *
     * @param integer $index
     * @param string $field
     * @return void
     */
    function deleteObjectFieldAce($index, $field);

    /**
     * Returns the primary key of this ACL
     *
     * @return integer
     */
    function getId();

    /**
     * Inserts a class-based ACE
     *
     * @param SecurityIdentityInterface $sid
     * @param integer $mask
     * @param integer $index
     * @param Boolean $granting
     * @param string $strategy
     * @return void
     */
    function insertClassAce(SecurityIdentityInterface $sid, $mask, $index = 0, $granting = true, $strategy = null);

    /**
     * Inserts a class-field-based ACE
     *
     * @param string $field
     * @param SecurityIdentityInterface $sid
     * @param integer $mask
     * @param integer $index
     * @param Boolean $granting
     * @param string $strategy
     * @return void
     */
    function insertClassFieldAce($field, SecurityIdentityInterface $sid, $mask, $index = 0, $granting = true, $strategy = null);

    /**
     * Inserts an object-based ACE
     *
     * @param SecurityIdentityInterface $sid
     * @param integer $mask
     * @param integer $index
     * @param Boolean $granting
     * @param string $strategy
     * @return void
     */
    function insertObjectAce(SecurityIdentityInterface $sid, $mask, $index = 0, $granting = true, $strategy = null);

    /**
     * Inserts an object-field-based ACE
     *
     * @param string $field
     * @param SecurityIdentityInterface $sid
     * @param integer $mask
     * @param integer $index
     * @param Boolean $granting
     * @param string $strategy
     * @return void
     */
    function insertObjectFieldAce($field, SecurityIdentityInterface $sid, $mask, $index = 0, $granting = true, $strategy = null);

    /**
     * Sets whether entries are inherited
     *
     * @param Boolean $boolean
     * @return void
     */
    function setEntriesInheriting($boolean);

    /**
     * Sets the parent ACL
     *
     * @param AclInterface $acl
     * @return void
     */
    function setParentAcl(AclInterface $acl);

    /**
     * Updates a class-based ACE
     *
     * @param integer $index
     * @param integer $mask
     * @param string $strategy if null the strategy should not be changed
     * @return void
     */
    function updateClassAce($index, $mask, $strategy = null);

    /**
     * Updates a class-field-based ACE
     *
     * @param integer $index
     * @param string $field
     * @param integer $mask
     * @param string $strategy if null the strategy should not be changed
     * @return void
     */
    function updateClassFieldAce($index, $field, $mask, $strategy = null);

    /**
     * Updates an object-based ACE
     *
     * @param integer $index
     * @param integer $mask
     * @param string $strategy if null the strategy should not be changed
     * @return void
     */
    function updateObjectAce($index, $mask, $strategy = null);

    /**
     * Updates an object-field-based ACE
     *
     * @param integer $index
     * @param string $field
     * @param integer $mask
     * @param string $strategy if null the strategy should not be changed
     * @return void
     */
    function updateObjectFieldAce($index, $field, $mask, $strategy = null);
}