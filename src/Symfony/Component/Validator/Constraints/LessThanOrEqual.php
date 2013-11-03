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
 * @Annotation
 *
 * @author Daniel Holmes <daniel@danielholmes.org>
 */
class LessThanOrEqual extends AbstractComparison
{
    const ERROR = '8bb5f334-d139-4821-ad09-00c63d55b5a8';

    public $message = 'This value should be less than or equal to {{ compared_value }}.';
}
