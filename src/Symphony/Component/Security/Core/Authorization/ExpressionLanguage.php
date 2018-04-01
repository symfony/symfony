<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Core\Authorization;

use Psr\Cache\CacheItemPoolInterface;
use Symphony\Component\ExpressionLanguage\ExpressionLanguage as BaseExpressionLanguage;

if (!class_exists(BaseExpressionLanguage::class)) {
    throw new \LogicException(sprintf('The "%s" class requires the "ExpressionLanguage" component. Try running "composer require symphony/expression-language".', ExpressionLanguage::class));
} else {
    /**
     * Adds some function to the default ExpressionLanguage.
     *
     * @author Fabien Potencier <fabien@symphony.com>
     *
     * @see ExpressionLanguageProvider
     */
    class ExpressionLanguage extends BaseExpressionLanguage
    {
        /**
         * {@inheritdoc}
         */
        public function __construct(CacheItemPoolInterface $cache = null, array $providers = array())
        {
            // prepend the default provider to let users override it easily
            array_unshift($providers, new ExpressionLanguageProvider());

            parent::__construct($cache, $providers);
        }
    }
}
