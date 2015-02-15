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
<<<<<<< HEAD
use Symfony\Component\Form\Extension\HttpFoundation\EventListener\BindRequestListener;
=======
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
use Symfony\Component\Form\RequestHandlerInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationRequestHandler;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormTypeHttpFoundationExtension extends AbstractTypeExtension
{
    /**
<<<<<<< HEAD
     * @var BindRequestListener
     */
    private $listener;

    /**
=======
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
     * @var RequestHandlerInterface
     */
    private $requestHandler;

    /**
     * @param RequestHandlerInterface $requestHandler
     */
    public function __construct(RequestHandlerInterface $requestHandler = null)
    {
<<<<<<< HEAD
        $this->listener = new BindRequestListener();
=======
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
        $this->requestHandler = $requestHandler ?: new HttpFoundationRequestHandler();
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
<<<<<<< HEAD
        $builder->addEventSubscriber($this->listener);
=======
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
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
