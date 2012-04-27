<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Csrf\EventListener;

use Symfony\Component\Form\Event\DataEvent;
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Form\FormFactoryInterface;

/**
 * Ensures the CSRF field.
 *
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 * @author Kris Wallsmith <kris@symfony.com>
 */
class EnsureCsrfFieldListener
{
    private $factory;
    private $name;
    private $intention;
    private $provider;

    /**
     * Constructor.
     *
     * @param FormFactoryInterface  $factory   The form factory
     * @param string                $name      A name for the CSRF field
     * @param string                $intention The intention string
     * @param CsrfProviderInterface $provider  The CSRF provider
     */
    public function __construct(FormFactoryInterface $factory, $name, $intention = null, CsrfProviderInterface $provider = null)
    {
        $this->factory = $factory;
        $this->name = $name;
        $this->intention = $intention;
        $this->provider = $provider;
    }

    /**
     * Ensures a root form has a CSRF field.
     *
     * This method should be connected to both form.pre_set_data and form.pre_bind.
     */
    public function ensureCsrfField(DataEvent $event)
    {
        $form = $event->getForm();

        $options = array();
        if ($this->intention) {
            $options['intention'] = $this->intention;
        }
        if ($this->provider) {
            $options['csrf_provider'] = $this->provider;
        }

        $form->add($this->factory->createNamed('csrf', $this->name, null, $options));
    }
}
