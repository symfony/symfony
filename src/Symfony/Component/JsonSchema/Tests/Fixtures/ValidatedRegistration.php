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

class ValidatedRegistration
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 20)]
    public string $username = '';

    #[Assert\Email]
    public ?string $email = null;

    #[Assert\Range(min: 18, max: 130)]
    public ?int $age = null;

    #[Assert\Positive]
    public ?int $score = null;

    #[Assert\Regex(pattern: '/^[A-Z]{2}$/')]
    public ?string $countryCode = null;

    #[Assert\Choice(choices: ['basic', 'premium'])]
    public ?string $plan = null;

    /** @var list<string> */
    #[Assert\Count(min: 1, max: 5)]
    #[Assert\Unique]
    public array $interests = [];

    #[Assert\Url]
    public ?string $website = null;

    #[Assert\NotBlank(groups: ['registration:create'])]
    #[Assert\Length(max: 10, groups: ['registration:create'])]
    public ?string $invitationCode = null;
}
