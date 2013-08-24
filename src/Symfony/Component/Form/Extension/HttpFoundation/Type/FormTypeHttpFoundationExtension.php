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
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationRequestHandler;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @since v2.1.0
 */
class FormTypeHttpFoundationExtension extends AbstractTypeExtension
{
    /**
     * @var BindRequestListener
     */
    private $listener;

    /**
     * @var HttpFoundationRequestHandler
     */
    private $requestHandler;

    /**
     * @since v2.1.0
     */
    public function __construct()
    {
        $this->listener = new BindRequestListener();
        $this->requestHandler = new HttpFoundationRequestHandler();
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventSubscriber($this->listener);
        $builder->setRequestHandler($this->requestHandler);
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function getExtendedType()
    {
        return 'form';
    }
}
