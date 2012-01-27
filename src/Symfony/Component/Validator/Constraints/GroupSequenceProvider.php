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
     * The name of the provider class
     * @var string
     */
    public $class;

    public function __construct(array $options)
    {
        $this->class = $options['value'];
    }
}
