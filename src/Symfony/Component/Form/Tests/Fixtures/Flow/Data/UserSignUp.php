<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures\Flow\Data;

use Symfony\Component\Validator\Constraints as Assert;

final class UserSignUp
{
    // personal step
    #[Assert\NotBlank(groups: ['personal'])]
    #[Assert\Length(min: 3, groups: ['personal'])]
    public ?string $firstName = null;
    public ?string $lastName = null;
    public bool $worker = false;

    // professional step
    #[Assert\NotBlank(groups: ['professional'])]
    #[Assert\Length(min: 3, groups: ['professional'])]
    public ?string $company = null;
    public ?string $role = null;

    // account step
    #[Assert\NotBlank(groups: ['account'])]
    #[Assert\Email(groups: ['account'])]
    public ?string $email = null;
    #[Assert\NotBlank(groups: ['account'])]
    #[Assert\PasswordStrength(groups: ['account'])]
    public ?string $password = null;

    public string $currentStep = '';
}
