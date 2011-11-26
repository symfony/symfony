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
     * Given choice list values this method returns the appropriate entities for it.
     * 
     * @param array $identifier
     * @param array $choiceListKeys Array of values of the select option, checkbox or radio button.
     * @return object[]
     */
    function getEntitiesByKeys(array $identifier, array $choiceListKeys);
    
    /**
     * Return an array of entities that are valid choices in the corresponding choice list.
     * 
     * @return array
     */
    function getEntities();
}
