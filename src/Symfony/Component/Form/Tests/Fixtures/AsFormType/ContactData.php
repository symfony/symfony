<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures\AsFormType;

use Symfony\Component\Form\Attribute\FormField;

abstract class ContactData
{
    use AddressTrait;

    #[FormField]
    public ?string $email = null;

    #[FormField(name: 'phoneNumber')]
    private ?string $phone = null;

    #[FormField]
    public ?string $company = null;

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }
}
