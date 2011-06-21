<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Csrf\Type;

class CsrfTypeTest extends TypeTestCase
{
    protected $provider;

    protected function setUp()
    {
        parent::setUp();

        $this->provider = $this->getMock('Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface');
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->provider = null;
    }

    protected function getNonRootForm()
    {
        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        $form->expects($this->any())
            ->method('isRoot')
            ->will($this->returnValue(false));

        return $form;
    }

    public function testGenerateCsrfToken()
    {
        $this->provider->expects($this->once())
            ->method('generateCsrfToken')
            ->with('%INTENTION%')
            ->will($this->returnValue('token'));

        $form = $this->factory->create('csrf', null, array(
            'csrf_provider' => $this->provider,
            'intention' => '%INTENTION%'
        ));

        $this->assertEquals('token', $form->getData());
    }

    public function testValidateTokenOnBind()
    {
        $this->provider->expects($this->once())
            ->method('isCsrfTokenValid')
            ->with('%INTENTION%', 'token')
            ->will($this->returnValue(true));

        $form = $this->factory->create('csrf', null, array(
            'csrf_provider' => $this->provider,
            'intention' => '%INTENTION%'
        ));
        $form->bind('token');

        $this->assertEquals('token', $form->getData());
    }

    public function testDontValidateTokenIfParentIsNotRoot()
    {
        $this->provider->expects($this->never())
            ->method('isCsrfTokenValid');

        $form = $this->factory->create('csrf', null, array(
            'csrf_provider' => $this->provider,
            'intention' => '%INTENTION%'
        ));
        $form->setParent($this->getNonRootForm());
        $form->bind('token');
    }

    public function testCsrfTokenIsRegeneratedIfValidationFails()
    {
        $this->provider->expects($this->at(0))
            ->method('generateCsrfToken')
            ->with('%INTENTION%')
            ->will($this->returnValue('token1'));
        $this->provider->expects($this->at(1))
            ->method('isCsrfTokenValid')
            ->with('%INTENTION%', 'invalid')
            ->will($this->returnValue(false));

        // The token is regenerated to avoid stalled tokens, for example when
        // the session ID changed
        $this->provider->expects($this->at(2))
            ->method('generateCsrfToken')
            ->with('%INTENTION%')
            ->will($this->returnValue('token2'));

        $form = $this->factory->create('csrf', null, array(
            'csrf_provider' => $this->provider,
            'intention' => '%INTENTION%'
        ));
        $form->bind('invalid');

        $this->assertEquals('token2', $form->getData());
    }
}
