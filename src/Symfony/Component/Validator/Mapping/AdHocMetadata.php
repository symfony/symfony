<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Mapping;

use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Exception\ValidatorException;

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class AdHocMetadata extends ElementMetadata
{
    public function __construct(array $constraints)
    {
        foreach ($constraints as $constraint) {
            if ($constraint instanceof Valid) {
                // Why can't the Valid constraint be executed directly?
                //
                // It cannot be executed like regular other constraints, because regular
                // constraints are only executed *if they belong to the validated group*.
                // The Valid constraint, on the other hand, is always executed and propagates
                // the group to the cascaded object. The propagated group depends on
                //
                //  * Whether a group sequence is currently being executed. Then the default
                //    group is propagated.
                //
                //  * Otherwise the validated group is propagated.

                throw new ValidatorException(sprintf(
                    'The constraint "%s" cannot be validated. Use the method '.
                    'validate() instead.',
                    get_class($constraint)
                ));
            }

            $this->addConstraint($constraint);
        }
    }

    public function getCascadingStrategy()
    {

    }

    public function getTraversalStrategy()
    {

    }
}
