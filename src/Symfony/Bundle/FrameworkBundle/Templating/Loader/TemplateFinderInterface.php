<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Templating\Loader;


/**
 * Interface for finding all the templates.
 *
 * @author Victor Berchet <victor@suumit.com>
 */
interface TemplateFinderInterface
{
    /**
     * Find all the templates.
     *
     * @return array An array of templates of type TemplateReferenceInterface
     */
    function findAllTemplates();
}