<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures\Flow;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Flow\Type\NavigatorFlowType;
use Symfony\Component\Form\Flow\Type\NextFlowType;
use Symfony\Component\Form\Flow\Type\ResetFlowType;
use Symfony\Component\Form\FormBuilderInterface;

class UserSignUpNavigatorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('skip', NextFlowType::class, [
            'clear_submission' => true,
            'include_if' => ['professional'],
        ]);

        $builder->add('reset', ResetFlowType::class);
    }

    public function getParent(): string
    {
        return NavigatorFlowType::class;
    }
}
