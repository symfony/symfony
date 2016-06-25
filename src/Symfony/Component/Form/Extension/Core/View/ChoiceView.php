<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\View;

@trigger_error('The '.__NAMESPACE__.'\ChoiceView class is deprecated since version 2.7 and will be removed in 3.0. Use Symfony\Component\Form\ChoiceList\View\ChoiceView instead.', E_USER_DEPRECATED);

/*
 * Represents a choice in templates.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated since version 2.7, to be removed in 3.0.
 *             Use {@link \Symfony\Component\Form\ChoiceList\View\ChoiceView} instead.
 */
class_exists('Symfony\Component\Form\ChoiceList\View\ChoiceView');
