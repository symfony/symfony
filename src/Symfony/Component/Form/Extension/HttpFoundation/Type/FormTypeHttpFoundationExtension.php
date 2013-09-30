<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\HttpFoundation\Type;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\HttpFoundation\EventListener\BindRequestListener;
use Symfony\Component\Form\RequestHandlerInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationRequestHandler;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormTypeHttpFoundationExtension extends AbstractTypeExtension
{
    /**
     * @var BindRequestListener
     */
    private $listener;

    /**
     * @var RequestHandlerInterface
     */
    private $requestHandler;

    /**
     * @param RequestHandlerInterface $requestHandler
     */
    public function __construct(RequestHandlerInterface $requestHandler = null)
    {
        $this->listener = new BindRequestListener();
        $this->requestHandler = $requestHandler ?: new HttpFoundationRequestHandler();
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventSubscriber($this->listener);
        $builder->setRequestHandler($this->requestHandler);
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return 'form';
    }
}
