<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Csrf\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Form\CallbackValidator;

class CsrfType extends AbstractType
{
    private $csrfProvider;

    public function __construct(CsrfProviderInterface $csrfProvider)
    {
        $this->csrfProvider = $csrfProvider;
    }

    public function buildForm(FormBuilder $builder, array $options)
    {
        $csrfProvider = $options['csrf_provider'];
        $pageId = $options['page_id'];

        $builder
        ->setData($csrfProvider->generateCsrfToken($pageId))
        ->addValidator(new CallbackValidator(
        function (FormInterface $form) use ($csrfProvider, $pageId) {
            if ((!$form->hasParent() || $form->getParent()->isRoot())
            && !$csrfProvider->isCsrfTokenValid($pageId, $form->getData())) {
                $form->addError(new FormError('The CSRF token is invalid. Please try to resubmit the form'));
                $form->setData($csrfProvider->generateCsrfToken($pageId));
            }
        }
        ));
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'csrf_provider' => $this->csrfProvider,
            'page_id' => null,
            'property_path' => false,
        );
    }

    public function getParent(array $options)
    {
        return 'hidden';
    }

    public function getName()
    {
        return 'csrf';
    }
}