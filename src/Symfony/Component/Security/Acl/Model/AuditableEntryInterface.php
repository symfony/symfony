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
 * ACEs can implement this interface if they support auditing capabilities.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface AuditableEntryInterface extends EntryInterface
{
    /**
     * Whether auditing for successful grants is turned on
     *
     * @return Boolean
     */
    function isAuditFailure();

    /**
     * Whether auditing for successful denies is turned on
     *
     * @return Boolean
     */
    function isAuditSuccess();
}