<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\CacheWarmer;

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
    public function findAllTemplates();
}
