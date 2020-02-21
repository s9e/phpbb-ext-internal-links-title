<?php declare(strict_types=1);

/**
* @package   s9e\internallinkstitle
* @copyright Copyright (c) 2020 The s9e authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\internallinkstitle;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use phpbb\config\config;
use phpbb\db\driver\driver_interface;
use s9e\TextFormatter\Parser\Tag;

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
			'core.posting_modify_post_data'           => 'onPosting',
			'core.text_formatter_s9e_configure_after' => 'onConfigure',
			'core.text_formatter_s9e_parser_setup'    => 'onParserSetup'
		];
	}

	public function onConfigure($event)
	{
		if (!isset($event['configurator']->tags['LINK_TEXT']))
		{
			return;
		}

		$tag = $event['configurator']->tags['LINK_TEXT'];
		if (!isset($tag->attributes['type']))
		{
			$type = $tag->attributes->add('type');
			$type->filterChain->append('#identifier');
			$type->required = false;
		}

		$tag->filterChain->prepend($this->helper->getFilter());
	}

	public function onParserSetup($event)
	{
		$event['parser']->get_parser()->registeredVars['s9e.internallinkstitle.helper'] = $this->helper;
	}

	public function onPosting($event)
	{
		if ($event['mode'] !== 'quote')
		{
			return;
		}

		$old = $event['post_data']['post_text'];
		$new = preg_replace(
			'((<URL(?= )[^>]* url="([^"]++)"[^>]*>)<LINK_TEXT text="([^"]++)" type="internal">[^<]++</LINK_TEXT></URL>)',
			'$1<s>[url=$2]</s>$3<e>[/url]</e></URL>',
			$old
		);
		if ($new !== $old)
		{
			$data               = $event['post_data'];
			$data['post_text']  = $new;
			$event['post_data'] = $data;
		}
	}
}