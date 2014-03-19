<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

/**
 * @group functional
 */
class FormTest extends WebTestCase
{
    /**
     * Tests the required single select form with the php engine.
     */
    public function testFormPhpChoiceSingleRequired()
    {
        $client = static::createClient(array('test_case' => 'Form', 'root_config' => 'config.yml'));
        $crawler = $client->request('GET', '/form_php/choice_single_required');
        $select = $crawler->filter('#form_choice');

        $this->assertEquals(3, $select->children()->count());
    }

    /**
     * Tests the required single select form with the twig engine.
     */
    public function testFormTwigChoiceSingleRequired()
    {
        $client = static::createClient(array('test_case' => 'Form', 'root_config' => 'config.yml'));
        $crawler = $client->request('GET', '/form_twig/choice_single_required');
        $select = $crawler->filter('#form_choice');

        $this->assertEquals(3, $select->children()->count());
    }

    /**
     * Tests the required multiple select form with the php engine.
     */
    public function testFormPhpChoiceMultipleRequired()
    {
        $client = static::createClient(array('test_case' => 'Form', 'root_config' => 'config.yml'));
        $crawler = $client->request('GET', '/form_php/choice_multiple_required');
        $select = $crawler->filter('#form_choice');

        $this->assertEquals(3, $select->children()->count());
    }

    /**
     * Tests the required multiple select form with the twig engine.
     */
    public function testFormTwigChoiceMultipleRequired()
    {
        $client = static::createClient(array('test_case' => 'Form', 'root_config' => 'config.yml'));
        $crawler = $client->request('GET', '/form_twig/choice_multiple_required');
        $select = $crawler->filter('#form_choice');

        $this->assertEquals(3, $select->children()->count());
    }

    /**
     * Tests the empty value option and label attribute of the single select
     * form with the php engine.
     */
    public function testFormPhpChoiceSingleNotRequired()
    {
        $client = static::createClient(array('test_case' => 'Form', 'root_config' => 'config.yml'));
        $crawler = $client->request('GET', '/form_php/choice_single_not_required');
        $select = $crawler->filter('#form_choice');

        $this->assertEquals(4, $select->children()->count());

        $firstOption = $select->children()->first();

        $this->assertEquals(' ', $firstOption->attr('label'));
        $this->assertEquals('', $firstOption->text());
    }

    /**
     * Tests the empty value option and label attribute of the single select
     * form with the twig engine.
     */
    public function testFormTwigChoiceSingleNotRequired()
    {
        $client = static::createClient(array('test_case' => 'Form', 'root_config' => 'config.yml'));
        $crawler = $client->request('GET', '/form_twig/choice_single_not_required');
        $select = $crawler->filter('#form_choice');

        $this->assertEquals(4, $select->children()->count());

        $firstOption = $select->children()->first();

        $this->assertEquals(' ', $firstOption->attr('label'));
        $this->assertEquals('', $firstOption->text());
    }

    /**
     * Tests the empty value option and label attribute of the multiple select
     * form with the php engine.
     */
    public function testFormPhpChoiceMultipleNotRequired()
    {
        $client = static::createClient(array('test_case' => 'Form', 'root_config' => 'config.yml'));
        $crawler = $client->request('GET', '/form_php/choice_multiple_not_required');
        $select = $crawler->filter('#form_choice');

        $this->assertEquals(3, $select->children()->count());
    }

    /**
     * Tests the empty value option and label attribute of the multiple select
     * form with the twig engine.
     */
    public function testFormTwigChoiceMultipleNotRequired()
    {
        $client = static::createClient(array('test_case' => 'Form', 'root_config' => 'config.yml'));
        $crawler = $client->request('GET', '/form_twig/choice_multiple_not_required');
        $select = $crawler->filter('#form_choice');

        $this->assertEquals(3, $select->children()->count());
    }

    /**
     * Test the empty value option and label attribute of the single select
     * form with the php engine.
     */
    public function testFormPhpChoiceSingleNotRequiredEmptyValue()
    {
        $client = static::createClient(array('test_case' => 'Form', 'root_config' => 'config.yml'));
        $crawler = $client->request('GET', '/form_php/choice_single_not_required_empty_value');
        $select = $crawler->filter('#form_choice');

        $this->assertEquals(4, $select->children()->count());

        $firstOption = $select->children()->first();

        $this->assertNull($firstOption->attr('label'));
        $this->assertEquals('Empty label', $firstOption->text());
    }

    /**
     * Test the empty value option and label attribute of the single select
     * form with the twig engine.
     */
    public function testFormTwigChoiceSingleNotRequiredEmptyValue()
    {
        $client = static::createClient(array('test_case' => 'Form', 'root_config' => 'config.yml'));
        $crawler = $client->request('GET', '/form_twig/choice_single_not_required_empty_value');
        $select = $crawler->filter('#form_choice');

        $this->assertEquals(4, $select->children()->count());

        $firstOption = $select->children()->first();

        $this->assertNull($firstOption->attr('label'));
        $this->assertEquals('Empty label', $firstOption->text());
    }
}
