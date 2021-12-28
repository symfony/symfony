<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\DataCollector;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

/**
 * Extracts arrays of information out of forms.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface FormDataExtractorInterface
{
    /**
     * Extracts the configuration data of a form.
     *
     * @return array
     */
    public function extractConfiguration(FormInterface $form);

    /**
     * Extracts the default data of a form.
     *
     * @return array
     */
    public function extractDefaultData(FormInterface $form);

    /**
     * Extracts the submitted data of a form.
     *
     * @return array
     */
    public function extractSubmittedData(FormInterface $form);

    /**
     * Extracts the view variables of a form.
     *
     * @return array
     */
    public function extractViewVariables(FormView $view);
}
