<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Acl\Domain;

use Symfony\Component\Security\Acl\Model\AclInterface;
use Symfony\Component\Security\Acl\Model\FieldEntryInterface;
use Symfony\Component\Security\Acl\Model\SecurityIdentityInterface;

/**
 * Field-aware ACE implementation which is auditable
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @since v2.0.0
 */
class FieldEntry extends Entry implements FieldEntryInterface
{
    private $field;

    /**
     * Constructor
     *
     * @param integer                   $id
     * @param AclInterface              $acl
     * @param string                    $field
     * @param SecurityIdentityInterface $sid
     * @param string                    $strategy
     * @param integer                   $mask
     * @param Boolean                   $granting
     * @param Boolean                   $auditFailure
     * @param Boolean                   $auditSuccess
     *
     * @since v2.0.0
     */
    public function __construct($id, AclInterface $acl, $field, SecurityIdentityInterface $sid, $strategy, $mask, $granting, $auditFailure, $auditSuccess)
    {
        parent::__construct($id, $acl, $sid, $strategy, $mask, $granting, $auditFailure, $auditSuccess);

        $this->field = $field;
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.0.0
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.0.0
     */
    public function serialize()
    {
        return serialize(array(
            $this->field,
            parent::serialize(),
        ));
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.0.0
     */
    public function unserialize($serialized)
    {
        list($this->field, $parentStr) = unserialize($serialized);
        parent::unserialize($parentStr);
    }
}
