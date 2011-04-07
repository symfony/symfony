<?php
namespace Symfony\Tests\Component\DomCrawler;

use Symfony\Component\DomCrawler\Crawler;

class CrawlerBugTest extends \PHPUnit_Framework_TestCase
{
	public function testFormUri()
	{
		$html = '<form action="#foo"><button name="mybutton" /></form>';

		$crawler = new Crawler($html, 'http://example.com/id/123');
		$form = $crawler->selectButton('mybutton')->form();

 		$actual = $form->getUri(); 	// bug: http://example.com/id/#foo
		$this->assertEquals('http://example.com/id/123#foo', $actual);
	}
}
