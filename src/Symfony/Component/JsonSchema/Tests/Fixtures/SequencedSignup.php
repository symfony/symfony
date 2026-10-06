<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests\Fixtures;

use Symfony\Component\Validator\Constraints as Assert;

#[Assert\GroupSequence(['SequencedSignup', 'strict'])]
class SequencedSignup
{
    #[Assert\Length(max: 50)]
    public string $name = '';

    #[Assert\NotBlank(groups: ['strict'])]
    public ?string $email = null;

    #[Assert\NotBlank(groups: ['other'])]
    public ?string $nickname = null;
}
