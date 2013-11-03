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
class EqualTo extends AbstractComparison
{
    const ERROR = 'f92206ec-df92-406d-931b-833343889cd7';

    public $message = 'This value should be equal to {{ compared_value }}.';
}
