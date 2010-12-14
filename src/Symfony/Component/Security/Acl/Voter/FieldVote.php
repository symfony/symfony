<?php

namespace Symfony\Component\Security\Acl\Voter;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * This class is a lightweight wrapper around field vote requests which does
 * not violate any interface contracts.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class FieldVote
{
    protected $domainObject;
    protected $field;

    public function __construct($domainObject, $field)
    {
        $this->domainObject = $domainObject;
        $this->field = $field;
    }

    public function getDomainObject()
    {
        return $this->domainObject;
    }

    public function getField()
    {
        return $this->field;
    }
}