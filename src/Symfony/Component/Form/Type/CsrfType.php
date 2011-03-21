<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Type;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Form\Validator\CallbackValidator;

class CsrfType extends AbstractType
{
    private $csrfProvider;

    public function __construct(CsrfProviderInterface $csrfProvider)
    {
        $this->csrfProvider = $csrfProvider;
    }

    public function configure(FormBuilder $builder, array $options)
    {
        $csrfProvider = $options['csrf_provider'];
        $pageId = $options['page_id'];

        $builder
            ->setData($csrfProvider->generateCsrfToken($pageId))
            ->addValidator(new CallbackValidator(
                function (FormInterface $field) use ($csrfProvider, $pageId) {
                    if (!$csrfProvider->isCsrfTokenValid($pageId, $field->getData())) {
                        $field->addError(new FormError('The CSRF token is invalid. Please try to resubmit the form'));
                    }
                }
            ));
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'csrf_provider' => $this->csrfProvider,
            'page_id' => null,
            'property_path' => null,
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