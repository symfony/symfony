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
 * Use this constraint to validate unique path among the app.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class PathAvailable extends Constraint
{
    public function __construct(public string $message = 'The path "{{ path }}" is reserved.', ?array $groups = null, $payload = null)
    {
        parent::__construct(null, $groups, $payload);
    }
}
