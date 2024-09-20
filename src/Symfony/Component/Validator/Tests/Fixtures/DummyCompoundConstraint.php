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

class DummyCompoundConstraint extends Compound
{
    protected function getConstraints(array $options): array
    {
        return [
            new NotBlank(),
            new Length(['max' => 3]),
            new Regex('/[a-z]+/'),
            new Regex('/[0-9]+/'),
        ];
    }
}
