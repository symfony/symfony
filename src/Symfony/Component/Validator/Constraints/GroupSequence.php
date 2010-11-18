<?php

namespace Symfony\Component\Validator\Constraints;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Annotation for group sequences
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class GroupSequence
{
    /**
     * The members of the sequence
     * @var array
     */
    public $groups;

    public function __construct(array $groups)
    {
        $this->groups = $groups['value'];
    }
}