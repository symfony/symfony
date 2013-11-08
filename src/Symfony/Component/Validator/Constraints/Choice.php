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
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class Choice extends Constraint
{
    const ERROR = '75d601e8-d9da-49a6-bf32-6df461244744';
    const ERROR_MULTIPLE = '695ad03b-1cdc-4b97-81d2-60c7de8424b5';
    const ERROR_MIN = '34f58897-1e70-4353-97d7-27ebd472406e';
    const ERROR_MAX = '73311798-3160-48b9-b71a-1502ba9e45d6';

    public $choices;
    public $callback;
    public $multiple = false;
    public $strict = false;
    public $min = null;
    public $max = null;
    public $message = 'The value you selected is not a valid choice.';
    public $multipleMessage = 'One or more of the given values is invalid.';
    public $minMessage = 'You must select at least {{ limit }} choice.|You must select at least {{ limit }} choices.';
    public $maxMessage = 'You must select at most {{ limit }} choice.|You must select at most {{ limit }} choices.';

    /**
     * {@inheritDoc}
     */
    public function getDefaultOption()
    {
        return 'choices';
    }
}
