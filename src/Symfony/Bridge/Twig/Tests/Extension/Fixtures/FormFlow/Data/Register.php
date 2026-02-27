<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension\Fixtures\FormFlow\Data;

final class Register
{
    // organization step
    public ?string $company = null;

    // credential step
    public ?string $email = null;
    public ?string $password = null;

    // confirmation step
    public bool $agreeTerms = false;

    public string $currentStep = 'organization';
}
