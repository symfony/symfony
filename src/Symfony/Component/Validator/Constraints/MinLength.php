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
 * @api
 *
 * @deprecated Deprecated since version 2.1, to be removed in 2.3.
 */
class MinLength extends Constraint
{
    public $message = 'This value is too short. It should have {{ limit }} character or more.|This value is too short. It should have {{ limit }} characters or more.';
    public $limit;
    public $charset = 'UTF-8';

    public function __construct($options = null)
    {
        trigger_error('MinLength is deprecated since version 2.1 and will be removed in 2.3. Use Length instead.', E_USER_DEPRECATED);

        parent::__construct($options);
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultOption()
    {
        return 'limit';
    }

    /**
     * {@inheritDoc}
     */
    public function getRequiredOptions()
    {
        return array('limit');
    }
}
