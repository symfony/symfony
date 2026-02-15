<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension\Fixtures\FormFlow;

use Symfony\Bridge\Twig\Tests\Extension\Fixtures\FormFlow\Data\Register;
use Symfony\Bridge\Twig\Tests\Extension\Fixtures\FormFlow\Step\RegisterConfirmationType;
use Symfony\Bridge\Twig\Tests\Extension\Fixtures\FormFlow\Step\RegisterCredentialsType;
use Symfony\Bridge\Twig\Tests\Extension\Fixtures\FormFlow\Step\RegisterOrganizationType;
use Symfony\Component\Form\Flow\AbstractFlowType;
use Symfony\Component\Form\Flow\DataStorage\InMemoryDataStorage;
use Symfony\Component\Form\Flow\FormFlowBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RegisterType extends AbstractFlowType
{
    public function buildFormFlow(FormFlowBuilderInterface $builder, array $options): void
    {
        $builder->addStep('organization', RegisterOrganizationType::class);
        $builder->addStep('credentials', RegisterCredentialsType::class);
        $builder->addStep('confirmation', RegisterConfirmationType::class);

        $builder->add('navigator', RegisterNavigatorType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Register::class,
            'data_storage' => new InMemoryDataStorage('register'),
            'step_property_path' => 'currentStep',
        ]);
    }
}
