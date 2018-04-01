<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Extension\HttpFoundation\Type;

use Symphony\Component\Form\AbstractTypeExtension;
use Symphony\Component\Form\RequestHandlerInterface;
use Symphony\Component\Form\FormBuilderInterface;
use Symphony\Component\Form\Extension\HttpFoundation\HttpFoundationRequestHandler;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormTypeHttpFoundationExtension extends AbstractTypeExtension
{
    private $requestHandler;

    public function __construct(RequestHandlerInterface $requestHandler = null)
    {
        $this->requestHandler = $requestHandler ?: new HttpFoundationRequestHandler();
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->setRequestHandler($this->requestHandler);
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return 'Symphony\Component\Form\Extension\Core\Type\FormType';
    }
}
