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
 * Annotation to define a group sequence provider
 *
 * @Annotation
 */
class GroupSequenceProvider
{
    /**
     * True if the group sequence provider should be used
     * @var boolean
     */
    public $active;

    public function __construct(array $options)
    {
        $this->active = (bool)$options['value'];
    }
}
