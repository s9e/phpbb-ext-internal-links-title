<?php declare(strict_types=1);

/**
* @package   s9e\internallinkstitle
* @copyright Copyright (c) 2020 The s9e authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\internallinkstitle;

use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\db\driver\driver_interface;
use s9e\TextFormatter\Parser;
use s9e\TextFormatter\Utils;

class helper
{
	protected $auth;
	protected $db;
	protected static $links = [];
	protected $postsTable;
	protected $regexp;
	protected $topicsTable;

	public function __construct(auth $auth, config $config, driver_interface $db, string $postsTable, string $topicsTable)
	{
		$this->auth   = $auth;
		$this->db     = $db;
		$this->regexp = '(^(?:\\w++:)?//'
		              . preg_quote($config['server_name'], '/')
		              . '(?::\\d++)?'
		              . preg_quote(rtrim($config['script_path'], '/'), '/')
		              . '/viewtopic\\..*?[&?](?<type>[pt])=(?<id>\\d++))i';

		$this->postsTable  = $postsTable;
		$this->topicsTable = $topicsTable;
	}

	public static function filterLinkText(string $text, self $helper): string
	{
		$title = $helper->getTitleByUrl($text);
		if ($title === '')
		{
			return $text;
		}

		$uniqid = uniqid('');
		self::$links[$uniqid] = ['title' => $title, 'url' => $text];

		return $uniqid;
	}

	public function getFilter(): string
	{
		return __CLASS__ . '::filterLinkText($attrValue, $s9e.internallinkstitle.helper)';
	}

	public function registerInstance(Parser $parser)
	{
		$parser->registeredVars['s9e.internallinkstitle.helper'] = $this;
	}

	public function replaceInternalLinkTitles(string $xml): string
	{
		return preg_replace_callback(
			'(<URL [^>]++>\\K(?:<s>[^<]++</s>)?<LINK_TEXT(?: [^=]++="[^"]*+")*? text="([0-9a-f]++)"[^>]*>[^<]++</LINK_TEXT>(?:<e>[^<]++</e>)?(?=</URL>))',
			function ($m)
			{
				$uniqid = $m[1];
				if (!isset(self::$links[$uniqid]))
				{
					return $m[0];
				}

				$url   = self::$links[$uniqid]['url'];
				$title = self::$links[$uniqid]['title'];

				return '<s>[url=' . $url . ']</s>' . self::escape($title) . '<e>[/url]</e>';
			},
			$xml
		);
	}

	protected static function escape(string $text): string
	{
		return Utils::encodeUnicodeSupplementaryCharacters(htmlentities($text, ENT_DISALLOWED | ENT_IGNORE | ENT_NOQUOTES | ENT_XML1, 'utf-8'));
	}

	protected function getPostData(int $postId): array
	{
		$sql    = 'SELECT forum_id, post_subject
		           FROM ' . $this->postsTable . '
		           WHERE post_id = ' . $postId;
		$result = $this->db->sql_query($sql);
		$row    = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ?: [];
	}

	protected function getTopicData(int $topicId): array
	{
		$sql    = 'SELECT forum_id, topic_title
		           FROM ' . $this->topicsTable . '
		           WHERE topic_id = ' . $topicId;
		$result = $this->db->sql_query($sql);
		$row    = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ?: [];
	}

	protected function getTitleByPostId(int $postId): string
	{
		$row = $this->getPostData($postId);
		if (empty($row))
		{
			return '';
		}

		$forumId = (int) $row['forum_id'];
		if (!$this->auth->acl_get('f_list', $forumId))
		{
			return '';
		}

		return html_entity_decode($row['post_subject'], ENT_QUOTES, 'utf-8');
	}

	protected function getTitleByTopicId(int $topicId): string
	{
		$row = $this->getTopicData($topicId);
		if (empty($row))
		{
			return '';
		}

		$forumId = (int) $row['forum_id'];
		if (!$this->auth->acl_get('f_list', $forumId))
		{
			return '';
		}

		return html_entity_decode($row['topic_title'], ENT_QUOTES, 'utf-8');
	}

	protected function getTitleByUrl(string $url): string
	{
		if (!preg_match($this->regexp, $url, $m))
		{
			return '';
		}
		$id = (int) $m['id'];

		return ($m['type'] === 't') ? $this->getTitleByTopicId($id) : $this->getTitleByPostId($id);
	}
}