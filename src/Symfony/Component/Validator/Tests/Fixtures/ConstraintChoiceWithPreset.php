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

use Symfony\Component\Validator\Constraints\Choice;

class ConstraintChoiceWithPreset extends Choice
{
    public $type;

    public function __construct(string $type)
    {
        parent::__construct($type);

        if ('A' === $this->type) {
            $this->choices = ['A', 'B', 'C'];
        } else {
            $this->choices = ['D', 'E', 'F'];
        }
    }

    public function getDefaultOption(): ?string
    {
        return 'type';
    }
}
