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

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 * @Target({"CLASS", "PROPERTY", "METHOD", "ANNOTATION"})
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Conditionally extends Composite
{
    /** @var string */
    public $condition;

    /** @var list<Constraint> */
    public $constraints;

    /**
     * @return list<string>
     */
    public function getRequiredOptions(): array
    {
        return ['condition', 'constraints'];
    }

    protected function getCompositeOption(): string
    {
        return 'constraints';
    }
}
