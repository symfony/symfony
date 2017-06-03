<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\ControllerMetadata\Configuration;

/**
 * The Security class handles the Security annotation.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @Annotation
 */
class Security extends ConfigurationAnnotation
{
    protected $expression;

    public function getExpression()
    {
        return $this->expression;
    }

    public function setExpression($expression)
    {
        $this->expression = $expression;
    }

    public function setValue($expression)
    {
        $this->setExpression($expression);
    }

    public function getAliasName()
    {
        return 'security';
    }

    public function allowArray()
    {
        return false;
    }
}
