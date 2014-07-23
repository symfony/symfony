<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\AutoLabel;

use Symfony\Component\Form\AbstractExtension;

/**
 * Extension for automated label generation.
 *
 * @since  2.7
 * @author Alexandre Salomé <alexandre.salome@gmail.com>
 */
class AutoLabelExtension extends AbstractExtension
{
    /**
     * @var string
     */
    private $autoLabel;

    /**
     * Constructs a new form extension.
     *
     * The argument "autoLabel" can have placeholders:
     *
     * - %type%    : the form type name (ex: text, choice, date)
     * - %name%    : the name of the form (ex: firstname)
     * - %fullname%: the full name of the form (ex: user_firstname)
     *
     * @param string $autoLabel a default label for forms
     */
    public function __construct($autoLabel)
    {
        $this->autoLabel = $autoLabel;
    }

    /**
     * {@inheritdoc}
     */
    protected function loadTypeExtensions()
    {
        return array(
            new Type\AutoLabelTypeExtension($this->autoLabel)
        );
    }
}
