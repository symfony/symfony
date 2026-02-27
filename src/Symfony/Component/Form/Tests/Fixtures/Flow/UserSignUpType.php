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

use Symfony\Component\Form\Flow\AbstractFlowType;
use Symfony\Component\Form\Flow\DataStorage\InMemoryDataStorage;
use Symfony\Component\Form\Flow\FormFlowBuilderInterface;
use Symfony\Component\Form\Tests\Fixtures\Flow\Data\UserSignUp;
use Symfony\Component\Form\Tests\Fixtures\Flow\Step\UserSignUpAccountType;
use Symfony\Component\Form\Tests\Fixtures\Flow\Step\UserSignUpPersonalType;
use Symfony\Component\Form\Tests\Fixtures\Flow\Step\UserSignUpProfessionalType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserSignUpType extends AbstractFlowType
{
    public function buildFormFlow(FormFlowBuilderInterface $builder, array $options): void
    {
        $skip = $options['data_class']
            ? static fn (UserSignUp $data) => !$data->worker
            : static fn (array $data) => !$data['worker'];

        $builder->addStep('personal', UserSignUpPersonalType::class);
        $builder->addStep('professional', UserSignUpProfessionalType::class, skip: $skip);
        $builder->addStep('account', UserSignUpAccountType::class);

        $builder->add('navigator', UserSignUpNavigatorType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserSignUp::class,
            'data_storage' => new InMemoryDataStorage('user_sign_up'),
            'step_property_path' => 'currentStep',
        ]);
    }
}
