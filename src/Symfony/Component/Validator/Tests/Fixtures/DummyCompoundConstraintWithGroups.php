<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Fixtures;

use Symfony\Component\Validator\Constraints\Compound;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class DummyCompoundConstraintWithGroups extends Compound
{
    protected function getConstraints(array $options): array
    {
        return [
            new NotBlank(groups: ['not_blank']),
            new Length(['max' => 3], groups: ['max_length']),
        ];
    }
}
