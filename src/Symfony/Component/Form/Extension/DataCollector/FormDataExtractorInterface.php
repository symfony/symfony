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
     */
    public function extractConfiguration(FormInterface $form): array;

    /**
     * Extracts the default data of a form.
     */
    public function extractDefaultData(FormInterface $form): array;

    /**
     * Extracts the submitted data of a form.
     */
    public function extractSubmittedData(FormInterface $form): array;

    /**
     * Extracts the view variables of a form.
     */
    public function extractViewVariables(FormView $view): array;
}
