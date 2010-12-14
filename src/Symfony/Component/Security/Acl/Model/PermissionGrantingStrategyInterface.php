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
 * Interface used by permission granting implementations.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface PermissionGrantingStrategyInterface
{
    /**
     * Determines whether access to a domain object is to be granted
     *
     * @param AclInterface $acl
     * @param array $masks
     * @param array $sids
     * @param Boolean $administrativeMode
     * @return Boolean
     */
    function isGranted(AclInterface $acl, array $masks, array $sids, $administrativeMode = false);

    /**
     * Determines whether access to a domain object's field is to be granted
     *
     * @param AclInterface $acl
     * @param string $field
     * @param array $masks
     * @param array $sids
     * @param Boolean $adminstrativeMode
     * @return Boolean
     */
    function isFieldGranted(AclInterface $acl, $field, array $masks, array $sids, $adminstrativeMode = false);
}