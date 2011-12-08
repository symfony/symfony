<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\SessionStorage;

use Symfony\Component\HttpFoundation\FlashBagInterface;
use Symfony\Component\HttpFoundation\AttributesBagInterface;

/**
 * NativeMemcacheSessionStorage.
 *
 * Session based on native PHP memcache database handler.
 *
 * @author Drak <drak@zikula.org>
 *
 * @api
 */
class NativeMemcacheSessionStorage extends AbstractSessionStorage
{
    /**
     * @var string
     */
    private $savePath;

    /**
     * Constructor.
     *
     * @param AttributesBagInterface $attributesBag   AttributesBagInterface instance.
     * @param FlashBagInterface      $flashBag        FlashbagInterface instance.
     * @param string                 $savePath        Save path.
     * @param array                  $options         Session options.
     */
    public function __construct(AttributesBagInterface $attributesBag, FlashBagInterface $flashBag, $savePath = 'tcp://127.0.0.1:11211?persistent=0', array $options = array())
    {
        if (!session_module_name('memcache')) {
            throw new \RuntimeException('PHP does not have "memcache" session module registered');
        }

        $this->savePath = $savePath;
        parent::__construct($attributesBag, $flashBag, $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function registerSaveHandlers()
    {
        ini_set('session.save_handlers', 'memcache');
        ini_set('session.save_path', $this->savePath);
    }
}
