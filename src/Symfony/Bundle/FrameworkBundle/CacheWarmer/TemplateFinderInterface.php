<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\CacheWarmer;

@trigger_error('The '.TemplateFinderInterface::class.' interface is deprecated since version 4.3 and will be removed in 5.0; use Twig instead.', \E_USER_DEPRECATED);

/**
 * Interface for finding all the templates.
 *
 * @author Victor Berchet <victor@suumit.com>
 *
 * @deprecated since version 4.3, to be removed in 5.0; use Twig instead.
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
