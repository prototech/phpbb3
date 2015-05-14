<?php
/**
*
* This file is part of the phpBB Forum Software package.
*
* @copyright (c) phpBB Limited <https://www.phpbb.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
* For full copyright and license information, please see
* the docs/CREDITS.txt file.
*
*/

namespace phpbb\textformatter;

/**
* Used to manipulate a parsed text
*/
interface utils_interface
{
	/**
	* Replace BBCodes and other formatting elements with whitespace
	*
	* NOTE: preserves smilies as text
	*
	* @param  string $text Parsed text
	* @return string       Plain text
	*/
	public function clean_formatting($text);

	/**
	* Remove given BBCode and its content, at given nesting depth
	*
	* @param  string  $text        Parsed text
	* @param  string  $bbcode_name BBCode's name
	* @param  integer $depth       Minimum nesting depth (number of parents of the same name)
	* @return string               Parsed text
	*/
	public function remove_bbcode($text, $bbcode_name, $depth = 0);

	/**
	* Return a parsed text to its original form
	*
	* @param  string $text Parsed text
	* @return string       Original plain text
	*/
	public function unparse($text);
}