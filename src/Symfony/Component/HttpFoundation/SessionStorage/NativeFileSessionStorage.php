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
 * NativeFileSessionStorage.
 *
 * Native session handler using PHP's built in file storage.
 *
 * @author Drak <drak@zikula.org>
 *
 * @api
 */
class NativeFileSessionStorage extends AbstractSessionStorage
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
    public function __construct(AttributesBagInterface $attributesBag, FlashBagInterface $flashBag, $savePath = null, array $options = array())
    {
        if (is_null($savePath)) {
            $savePath = sys_get_temp_dir();
        }

        if (!is_dir($savePath)) {
            mkdir($savePath, 0777, true);
        }

        $this->savePath = $savePath;

        parent::__construct($attributesBag, $flashBag, $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function registerSaveHandlers()
    {
        ini_set('session.save_handlers', 'files');
        ini_set('session.save_path', $this->savePath);
    }
}
