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
class GreaterThanOrEqual extends AbstractComparison
{
    const ERROR = 'd53fb887-5eef-4c1c-8898-02ca753e949b';

    public $message = 'This value should be greater than or equal to {{ compared_value }}.';
}
