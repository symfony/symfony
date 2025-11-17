<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Flow\Type;

use Symfony\Component\Form\Flow\AbstractButtonFlowType;
use Symfony\Component\Form\Flow\ButtonFlowInterface;
use Symfony\Component\Form\Flow\FormFlowCursor;
use Symfony\Component\Form\Flow\FormFlowInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PreviousFlowType extends AbstractButtonFlowType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->setAttribute('action', 'previous');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'handler' => fn (mixed $data, ButtonFlowInterface $button, FormFlowInterface $flow) => $flow->movePrevious($button->getViewData()),
            'include_if' => fn (FormFlowCursor $cursor): bool => $cursor->canMoveBack(),
            'clear_submission' => true,
        ]);
    }
}
