<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Fixtures\Annotation;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\GroupSequenceProviderInterface;

/**
 * @Assert\GroupSequenceProvider
 */
class GroupSequenceProviderEntity implements GroupSequenceProviderInterface
{
    public $firstName;
    public $lastName;

    protected $sequence = [];

    public function __construct($sequence)
    {
        $this->sequence = $sequence;
    }

    public function getGroupSequence(): array|GroupSequence
    {
        return $this->sequence;
    }
}
