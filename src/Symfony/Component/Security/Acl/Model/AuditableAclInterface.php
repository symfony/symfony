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
 * This interface adds auditing capabilities to the ACL.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface AuditableAclInterface extends MutableAclInterface
{
    /**
     * Updates auditing for class-based ACE
     *
     * @param integer $index
     * @param Boolean $auditSuccess
     * @param Boolean $auditFailure
     * @return void
     */
    function updateClassAuditing($index, $auditSuccess, $auditFailure);

    /**
     * Updates auditing for class-field-based ACE
     *
     * @param integer $index
     * @param string $field
     * @param Boolean $auditSuccess
     * @param Boolean $auditFailure
     * @return void
     */

    function updateClassFieldAuditing($index, $field, $auditSuccess, $auditFailure);

    /**
     * Updates auditing for object-based ACE
     *
     * @param integer $index
     * @param Boolean $auditSuccess
     * @param Boolean $auditFailure
     * @return void
     */
    function updateObjectAuditing($index, $auditSuccess, $auditFailure);

    /**
     * Updates auditing for object-field-based ACE
     *
     * @param integer $index
     * @param string $field
     * @param Boolean $auditSuccess
     * @param Boolean $auditFailure
     * @return void
     */
    function updateObjectFieldAuditing($index, $field, $auditSuccess, $auditFailure);
}