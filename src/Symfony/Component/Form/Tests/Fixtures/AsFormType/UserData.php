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

use Symfony\Component\Form\Attribute\AsFormType;
use Symfony\Component\Form\Attribute\FormField;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

#[AsFormType(options: ['label' => 'User'])]
class UserData
{
    #[FormField]
    public ?string $name = null;

    #[FormField(TextareaType::class, ['label' => 'Bio'])]
    public ?string $bio = null;

    #[FormField(name: 'publicName')]
    public ?string $internalName = null;

    public ?string $notAField = null;
}
