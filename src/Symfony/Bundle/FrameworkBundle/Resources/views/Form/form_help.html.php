<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if (!empty($help)): ?>
    <p id="<?php echo $view->escape($id); ?>_help" class="help-text"><?php echo $view->escape(false !== $translation_domain ? $view['translator']->trans($help, array(), $translation_domain) : $help); ?></p>
<?php endif; ?>
