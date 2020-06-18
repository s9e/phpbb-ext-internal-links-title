<?php declare(strict_types=1);

/**
* @package   s9e\internallinkstitle
* @copyright Copyright (c) 2020 The s9e authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\internallinkstitle;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	protected $helper;

	public function __construct(helper $helper)
	{
		$this->helper = $helper;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.text_formatter_s9e_configure_after' => 'onConfigure',
			'core.text_formatter_s9e_parse_after'     => 'afterParse',
			'core.text_formatter_s9e_parser_setup'    => 'onParserSetup'
		];
	}

	public function afterParse($event)
	{
		$event['xml'] = $this->helper->replaceEncodedLinkText($event['xml']);
	}

	public function onConfigure($event)
	{
		if (!isset($event['configurator']->tags['LINK_TEXT']))
		{
			return;
		}

		$event['configurator']->tags['LINK_TEXT']->attributes['text']->filterChain
			->prepend($this->helper->getFilter());
	}

	public function onParserSetup($event)
	{
		$this->helper->registerInstance($event['parser']->get_parser());
	}
}