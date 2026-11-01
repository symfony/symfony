<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Validator\Type;

use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationRequestHandler;
use Symfony\Component\Form\Extension\Validator\Constraints\Form;
use Symfony\Component\HttpFoundation\Request;

class CollectionTypeValidatorExtensionTest extends BaseValidatorExtensionTestCase
{
    protected function createForm(array $options = [])
    {
        return $this->factory->create(CollectionType::class, null, $options);
    }

    public function testInvalidSubmittedEntryNamesProduceExtraFieldsError()
    {
        $form = $this->factory->createNamedBuilder('tags', CollectionType::class, ['existing' => 'old'], [
            'entry_type' => TextType::class,
            'allow_add' => true,
        ])
            ->setRequestHandler(new HttpFoundationRequestHandler())
            ->getForm();
        parse_str('tags[existing]=updated&tags[a.b]=invalid&tags[01]=added', $data);

        $form->handleRequest(Request::create('/', 'POST', $data));

        $this->assertFalse($form->isValid());
        $this->assertSame(['existing' => 'updated', '01' => 'added'], $form->getData());
        $this->assertSame(['a.b' => 'invalid'], $form->getExtraData());
        $this->assertCount(1, $form->getErrors());
        $error = $form->getErrors()->current();
        $this->assertSame(Form::NO_SUCH_FIELD_ERROR, $error->getCause()->getCode());
        $this->assertSame($form, $error->getOrigin());
        $view = $form->createView();
        $this->assertSame($error, $view->vars['errors']->current());
        $this->assertSame('tags[01]', $view['01']->vars['full_name']);
    }

    public function testInvalidMessage()
    {
        $form = $this->createForm();

        $this->assertSame('The collection is invalid.', $form->getConfig()->getOption('invalid_message'));
    }
}
