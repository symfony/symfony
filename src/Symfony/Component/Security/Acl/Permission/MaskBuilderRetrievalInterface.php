<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Acl\Permission;

/**
 * Retrieves the MaskBuilder
 */
interface MaskBuilderRetrievalInterface
{
    /**
     * Returns a new instance of the MaskBuilder used in the permissionMap
     *
     * @return MaskBuilderInterface
     */
    public function getMaskBuilder();
}
