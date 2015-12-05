<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Form;

use Symfony\Bundle\FrameworkBundle\Exception\LogicException;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormFactoryInterface;

/**
 * Form integration for controller classes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexander M. Turek <me@derrabus.de>
 */
trait FormHelperTrait
{
    /**
     * @var FormFactoryInterface
     */
    protected $formFactory;

    /**
     * @return FormFactoryInterface
     */
    protected function getFormFactory()
    {
        if ($this->formFactory === null) {
            if (!isset($this->container)) {
                throw new LogicException('Unable to load the form factory. Please either set the $formFactory property or make'.__CLASS__.' container-aware.');
            }

            $this->formFactory = $this->container->get('form.factory');
        }

        return $this->formFactory;
    }

    /**
     * Creates and returns a Form instance from the type of the form.
     *
     * @param string $type    The fully qualified class name of the form type
     * @param mixed  $data    The initial data for the form
     * @param array  $options Options for the form
     *
     * @return Form
     */
    protected function createForm($type, $data = null, array $options = array())
    {
        return $this->getFormFactory()->create($type, $data, $options);
    }

    /**
     * Creates and returns a form builder instance.
     *
     * @param mixed $data    The initial data for the form
     * @param array $options Options for the form
     *
     * @return FormBuilder
     */
    protected function createFormBuilder($data = null, array $options = array())
    {
        return $this->getFormFactory()->createBuilder(FormType::class, $data, $options);
    }
}
