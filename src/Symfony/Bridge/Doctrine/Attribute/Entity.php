<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Attribute;

use Symfony\Component\HttpKernel\Attribute\ParamConverter;

/**
 * Doctrine-specific ParamConverter with an easier syntax.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class Entity extends ParamConverter
{
    public function setExpr($expr)
    {
        $options = $this->getOptions();
        $options['expr'] = $expr;

        $this->setOptions($options);
    }

    public function __construct(
        string $name,
        string $expr = null,
        string $class = null,
        array $options = [],
        bool $isOptional = false,
        string $converter = null
    ) {
        parent::__construct($name, $class, $options, $isOptional, $converter);

        $this->setExpr($expr);
    }
}
