<?php

namespace Symfony\Tests\Components\Form;

use Symfony\Components\Form\ChoiceField;

class ChoiceFieldTest extends \PHPUnit_Framework_TestCase
{
    protected $choices = array(
        'a' => 'Bernhard',
        'b' => 'Fabien',
        'c' => 'Kris',
        'd' => 'Jon',
        'e' => 'Roman',
    );

    protected $preferredChoices = array('d', 'e');

    protected $groupedChoices = array(
        'Symfony' => array(
            'a' => 'Bernhard',
            'b' => 'Fabien',
            'c' => 'Kris',
        ),
        'Doctrine' => array(
            'd' => 'Jon',
            'e' => 'Roman',
        )
    );

    public function testBindSingleNonExpanded()
    {
        $field = new ChoiceField('name', array(
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->choices,
        ));

        $field->bind('b');

        $this->assertEquals('b', $field->getData());
        $this->assertEquals('b', $field->getDisplayedData());
    }

    public function testRenderSingleNonExpanded()
    {
        $field = new ChoiceField('name', array(
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->choices,
        ));

        $field->setData('b');

        $html = <<<EOF
<select id="name" name="name" class="foobar">
<option value="a">Bernhard</option>
<option value="b" selected="selected">Fabien</option>
<option value="c">Kris</option>
<option value="d">Jon</option>
<option value="e">Roman</option>
</select>
EOF;

        $this->assertEquals($html, $field->render(array(
            'class' => 'foobar',
        )));
    }

    public function testRenderSingleNonExpanded_translateChoices()
    {
        $translator = $this->getMock('Symfony\Components\I18N\TranslatorInterface');
        $translator->expects($this->any())
                             ->method('translate')
                             ->will($this->returnCallback(function($text) {
                                 return 'translated['.$text.']';
                             }));

        $field = new ChoiceField('name', array(
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->choices,
            'translate_choices' => true,
        ));

        $field->setTranslator($translator);
        $field->setData('b');

        $html = <<<EOF
<select id="name" name="name" class="foobar">
<option value="a">translated[Bernhard]</option>
<option value="b" selected="selected">translated[Fabien]</option>
<option value="c">translated[Kris]</option>
<option value="d">translated[Jon]</option>
<option value="e">translated[Roman]</option>
</select>
EOF;

        $this->assertEquals($html, $field->render(array(
            'class' => 'foobar',
        )));
    }

    public function testRenderSingleNonExpanded_disabled()
    {
        $field = new ChoiceField('name', array(
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->choices,
            'disabled' => true,
        ));


        $html = <<<EOF
<select id="name" name="name" disabled="disabled">
<option value="a">Bernhard</option>
<option value="b">Fabien</option>
<option value="c">Kris</option>
<option value="d">Jon</option>
<option value="e">Roman</option>
</select>
EOF;

        $this->assertEquals($html, $field->render());
    }

    public function testRenderSingleNonExpandedWithPreferred()
    {
        $field = new ChoiceField('name', array(
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->choices,
            'preferred_choices' => $this->preferredChoices,
            'separator' => '---',
        ));

        $field->setData('d');

        $html = <<<EOF
<select id="name" name="name">
<option value="d" selected="selected">Jon</option>
<option value="e">Roman</option>
<option disabled="disabled">---</option>
<option value="a">Bernhard</option>
<option value="b">Fabien</option>
<option value="c">Kris</option>
</select>
EOF;

        $this->assertEquals($html, $field->render());
    }

    public function testRenderSingleNonExpandedWithGroups()
    {
        $field = new ChoiceField('name', array(
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->groupedChoices,
        ));

        $html = <<<EOF
<select id="name" name="name">
<optgroup label="Symfony">
<option value="a">Bernhard</option>
<option value="b">Fabien</option>
<option value="c">Kris</option>
</optgroup>
<optgroup label="Doctrine">
<option value="d">Jon</option>
<option value="e">Roman</option>
</optgroup>
</select>
EOF;

        $this->assertEquals($html, $field->render());
    }

    public function testRenderSingleNonExpandedNonRequired()
    {
        $field = new ChoiceField('name', array(
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->choices,
            'empty_value' => 'empty',
        ));

        $field->setData(null);
        $field->setRequired(false);

        $html = <<<EOF
<select id="name" name="name">
<option value="" selected="selected">empty</option>
<option value="a">Bernhard</option>
<option value="b">Fabien</option>
<option value="c">Kris</option>
<option value="d">Jon</option>
<option value="e">Roman</option>
</select>
EOF;

        $this->assertEquals($html, $field->render());
    }

    public function testBindMultipleNonExpanded()
    {
        $field = new ChoiceField('name', array(
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->choices,
        ));

        $field->bind(array('a', 'b'));

        $this->assertEquals(array('a', 'b'), $field->getData());
        $this->assertEquals(array('a', 'b'), $field->getDisplayedData());
    }

    public function testRenderMultipleNonExpanded()
    {
        $field = new ChoiceField('name', array(
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->choices,
        ));

        $field->setData(array('a', 'b'));

        $html = <<<EOF
<select id="name" name="name[]" multiple="multiple">
<option value="a" selected="selected">Bernhard</option>
<option value="b" selected="selected">Fabien</option>
<option value="c">Kris</option>
<option value="d">Jon</option>
<option value="e">Roman</option>
</select>
EOF;

        $this->assertEquals($html, $field->render());
    }

    public function testBindSingleExpanded()
    {
        $field = new ChoiceField('name', array(
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->choices,
        ));

        $field->bind('b');

        $this->assertEquals('b', $field->getData());
        $this->assertEquals('b', $field->getDisplayedData());
    }

    public function testRenderSingleExpanded()
    {
        $field = new ChoiceField('name', array(
            'multiple' => false,
            'expanded' => true,
            'choices' => $this->choices,
        ));

        $field->setData('b');

        $html = <<<EOF
<input id="name_a" name="name" value="a" type="radio" /> <label for="name_a">Bernhard</label>
<input id="name_b" name="name" value="b" checked="checked" type="radio" /> <label for="name_b">Fabien</label>
<input id="name_c" name="name" value="c" type="radio" /> <label for="name_c">Kris</label>
<input id="name_d" name="name" value="d" type="radio" /> <label for="name_d">Jon</label>
<input id="name_e" name="name" value="e" type="radio" /> <label for="name_e">Roman</label>

EOF;

        $this->assertEquals($html, $field->render());
    }

    public function testRenderSingleExpanded_translateChoices()
    {
        $translator = $this->getMock('Symfony\Components\I18N\TranslatorInterface');
        $translator->expects($this->any())
                             ->method('translate')
                             ->will($this->returnCallback(function($text) {
                                 return 'translated['.$text.']';
                             }));

        $field = new ChoiceField('name', array(
            'multiple' => false,
            'expanded' => true,
            'choices' => $this->choices,
            'translate_choices' => true,
        ));

        $field->setTranslator($translator);
        $field->setData('b');

        $html = <<<EOF
<input id="name_a" name="name" value="a" type="radio" /> <label for="name_a">translated[Bernhard]</label>
<input id="name_b" name="name" value="b" checked="checked" type="radio" /> <label for="name_b">translated[Fabien]</label>
<input id="name_c" name="name" value="c" type="radio" /> <label for="name_c">translated[Kris]</label>
<input id="name_d" name="name" value="d" type="radio" /> <label for="name_d">translated[Jon]</label>
<input id="name_e" name="name" value="e" type="radio" /> <label for="name_e">translated[Roman]</label>

EOF;

        $this->assertEquals($html, $field->render());
    }

    public function testRenderSingleExpandedWithPreferred()
    {
        $field = new ChoiceField('name', array(
            'multiple' => false,
            'expanded' => true,
            'choices' => $this->choices,
            'preferred_choices' => $this->preferredChoices,
        ));

        $html = <<<EOF
<input id="name_d" name="name" value="d" type="radio" /> <label for="name_d">Jon</label>
<input id="name_e" name="name" value="e" type="radio" /> <label for="name_e">Roman</label>
<input id="name_a" name="name" value="a" type="radio" /> <label for="name_a">Bernhard</label>
<input id="name_b" name="name" value="b" type="radio" /> <label for="name_b">Fabien</label>
<input id="name_c" name="name" value="c" type="radio" /> <label for="name_c">Kris</label>

EOF;

        $this->assertEquals($html, $field->render());
    }

    public function testBindMultipleExpanded()
    {
        $field = new ChoiceField('name', array(
            'multiple' => true,
            'expanded' => true,
            'choices' => $this->choices,
        ));

        $field->bind(array('a' => 'a', 'b' => 'b'));

        $this->assertSame(array('a', 'b'), $field->getData());
        $this->assertSame(true, $field['a']->getData());
        $this->assertSame(true, $field['b']->getData());
        $this->assertSame(null, $field['c']->getData());
        $this->assertSame(null, $field['d']->getData());
        $this->assertSame(null, $field['e']->getData());
        $this->assertSame('1', $field['a']->getDisplayedData());
        $this->assertSame('1', $field['b']->getDisplayedData());
        $this->assertSame('', $field['c']->getDisplayedData());
        $this->assertSame('', $field['d']->getDisplayedData());
        $this->assertSame('', $field['e']->getDisplayedData());
        $this->assertSame(array('a' => '1', 'b' => '1', 'c' => '', 'd' => '', 'e' => ''), $field->getDisplayedData());
    }

    public function testRenderMultipleExpanded()
    {
        $field = new ChoiceField('name', array(
            'multiple' => true,
            'expanded' => true,
            'choices' => $this->choices,
        ));

        $field->setData(array('a', 'b'));

        $html = <<<EOF
<input id="name_a" name="name[a]" value="a" checked="checked" type="checkbox" /> <label for="name_a">Bernhard</label>
<input id="name_b" name="name[b]" value="b" checked="checked" type="checkbox" /> <label for="name_b">Fabien</label>
<input id="name_c" name="name[c]" value="c" type="checkbox" /> <label for="name_c">Kris</label>
<input id="name_d" name="name[d]" value="d" type="checkbox" /> <label for="name_d">Jon</label>
<input id="name_e" name="name[e]" value="e" type="checkbox" /> <label for="name_e">Roman</label>

EOF;

        $this->assertEquals($html, $field->render());
    }
}