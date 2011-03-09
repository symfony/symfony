<?php

namespace Symfony\Component\Form;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Form\FieldFactory\FieldFactoryInterface;
use Symfony\Component\Form\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Validator\ValidatorInterface;

/**
 * Stores options for creating new forms
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
interface FormContextInterface
{
    /**
     * Returns the options used for creating a new form
     *
     * @return array  The form options
     */
    public function getOptions();
}