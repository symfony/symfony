<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Acl\Voter;

/**
 * This class is a lightweight wrapper around field vote requests which does
 * not violate any interface contracts.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @since v2.0.0
 */
class FieldVote
{
    private $domainObject;
    private $field;

    /**
     * @since v2.0.0
     */
    public function __construct($domainObject, $field)
    {
        $this->domainObject = $domainObject;
        $this->field = $field;
    }

    /**
     * @since v2.0.0
     */
    public function getDomainObject()
    {
        return $this->domainObject;
    }

    /**
     * @since v2.0.0
     */
    public function getField()
    {
        return $this->field;
    }
}
