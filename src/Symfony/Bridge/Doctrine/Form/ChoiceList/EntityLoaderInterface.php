<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Form\ChoiceList;

/**
 * Custom loader for entities in the choice list.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
interface EntityLoaderInterface
{
    /**
     * Return an array of entities that are valid choices in the corresponding choice list.
     *
     * @return array
     */
    function getEntities();
}
