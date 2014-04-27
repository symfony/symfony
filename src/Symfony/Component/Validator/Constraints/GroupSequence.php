<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

/**
 * Annotation for group sequences
 *
 * @Annotation
 * @Target({"CLASS", "ANNOTATION"})
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
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
