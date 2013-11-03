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
class NotEqualTo extends AbstractComparison
{
    const ERROR = 'fc9de329-a897-45c9-8fea-83ea2d1c8aed';

    public $message = 'This value should not be equal to {{ compared_value }}.';
}
