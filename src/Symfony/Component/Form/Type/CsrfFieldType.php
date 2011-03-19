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

use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\FieldBuilder;
use Symfony\Component\Form\FieldError;
use Symfony\Component\Form\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Form\DataValidator\CallbackValidator;

class CsrfFieldType extends AbstractFieldType
{
    private $csrfProvider;

    public function __construct(CsrfProviderInterface $csrfProvider)
    {
        $this->csrfProvider = $csrfProvider;
    }

    public function configure(FieldBuilder $builder, array $options)
    {
        $csrfProvider = $options['csrf_provider'];
        $pageId = $options['page_id'];

        $builder
            ->setData($csrfProvider->generateCsrfToken($pageId))
            ->setDataValidator(new CallbackValidator(
                function (FieldInterface $field) use ($csrfProvider, $pageId) {
                    if (!$csrfProvider->isCsrfTokenValid($pageId, $field->getData())) {
                        // FIXME this error is currently not displayed
                        // it needs to be passed up to the form
                        $field->addError(new FieldError('The CSRF token is invalid. Please try to resubmit the form'));
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