<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Storage;

trigger_deprecation('symfony/templating', '6.4', '"%s" is deprecated since version 6.4 and will be removed in 7.0. Use Twig instead.', Storage::class);

/**
 * Storage is the base class for all storage classes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since Symfony 6.4, use Twig instead
 */
abstract class Storage
{
    protected $template;

    /**
     * @param string $template The template name
     */
    public function __construct(string $template)
    {
        $this->template = $template;
    }

    /**
     * Returns the object string representation.
     */
    public function __toString(): string
    {
        return $this->template;
    }

    /**
     * Returns the content of the template.
     */
    abstract public function getContent(): string;
}
