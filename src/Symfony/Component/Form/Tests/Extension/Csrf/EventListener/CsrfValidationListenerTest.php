<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Csrf\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\DataMapper\DataMapper;
use Symfony\Component\Form\Extension\Csrf\EventListener\CsrfValidationListener;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormFactoryBuilder;
use Symfony\Component\Form\Util\ServerParams;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

class CsrfValidationListenerTest extends TestCase
{
    protected $dispatcher;
    protected $factory;
    protected $tokenManager;
    protected $form;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
        $this->factory = (new FormFactoryBuilder())->getFormFactory();
        $this->tokenManager = new CsrfTokenManager();
        $this->form = $this->getBuilder()
            ->setDataMapper(new DataMapper())
            ->getForm();
    }

    protected function tearDown(): void
    {
        $this->dispatcher = null;
        $this->factory = null;
        $this->tokenManager = null;
        $this->form = null;
    }

    protected function getBuilder()
    {
        return new FormBuilder('post', null, $this->dispatcher, $this->factory, ['compound' => true]);
    }

    // https://github.com/symfony/symfony/pull/5838
    public function testStringFormData()
    {
        $data = 'XP4HUzmHPi';
        $event = new FormEvent($this->form, $data);

        $validation = new CsrfValidationListener('csrf', $this->tokenManager, 'unknown', 'Invalid.');
        $validation->preSubmit($event);

        // Validate accordingly
        $this->assertSame($data, $event->getData());
    }

    public function testArrayCsrfToken()
    {
        $event = new FormEvent($this->form, ['csrf' => []]);

        $validation = new CsrfValidationListener('csrf', $this->tokenManager, 'unknown', 'Invalid.');
        $validation->preSubmit($event);

        $this->assertNotEmpty($this->form->getErrors());
    }

    public function testMaxPostSizeExceeded()
    {
        $event = new FormEvent($this->form, ['csrf' => 'token']);
        $validation = new CsrfValidationListener('csrf', $this->tokenManager, 'unknown', 'Error message', null, null, new ServerParamsPostMaxSizeExceeded());

        $validation->preSubmit($event);
        $this->assertEmpty($this->form->getErrors());
    }
}

class ServerParamsPostMaxSizeExceeded extends ServerParams
{
    public function hasPostMaxSizeBeenExceeded(): bool
    {
        return true;
    }
}
