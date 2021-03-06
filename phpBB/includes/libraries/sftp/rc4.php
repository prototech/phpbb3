<?php
/**
*
* @package sftp
* @version $Id$
* @copyright (c) 2006 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

/**
* Code from http://phpseclib.sourceforge.net/
*
* Modified by phpBB Group to meet our coding standards
* and being able to integrate into phpBB
*
* Pure-PHP implementation of RC4
*
* Copyright 2007-2009 TerraFrost <terrafrost@php.net>
* Copyright 2009+ phpBB
*
* @package sftp
* @author  TerraFrost <terrafrost@php.net>
*/

/**#@+
 * @access private
 * @see rc4::__construct()
 */
/**
 * Toggles the internal implementation
 */
define('CRYPT_RC4_MODE_INTERNAL', 1);
/**
 * Toggles the mcrypt implementation
 */
define('CRYPT_RC4_MODE_MCRYPT', 2);
/**#@-*/

/**#@+
 * @access private
 * @see rc4::_crypt()
 */
define('CRYPT_RC4_ENCRYPT', 0);
define('CRYPT_RC4_DECRYPT', 1);
/**#@-*/

/**
 * Pure-PHP implementation of RC4.
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 * @version 0.1.0
 * @access  public
 * @package rc4
 */
class rc4
{
	/**
	 * The Key
	 *
	 * @see rc4::set_key()
	 * @var String
	 * @access private
	 */
	var $key = "\0";

	/**
	 * The Key Stream for encryption
	 *
	 * If CRYPT_RC4_MODE == CRYPT_RC4_MODE_MCRYPT, this will be equal to the mcrypt object
	 *
	 * @see rc4::set_key()
	 * @var Array
	 * @access private
	 */
	var $encryptStream = false;

	/**
	 * The Key Stream for decryption
	 *
	 * If CRYPT_RC4_MODE == CRYPT_RC4_MODE_MCRYPT, this will be equal to the mcrypt object
	 *
	 * @see rc4::set_key()
	 * @var Array
	 * @access private
	 */
	var $decryptStream = false;

	/**
	 * The $i and $j indexes for encryption
	 *
	 * @see rc4::_crypt()
	 * @var Integer
	 * @access private
	 */
	var $encryptIndex = 0;

	/**
	 * The $i and $j indexes for decryption
	 *
	 * @see rc4::_crypt()
	 * @var Integer
	 * @access private
	 */
	var $decryptIndex = 0;

	/**
	 * MCrypt parameters
	 *
	 * @see rc4::set_mcrypt()
	 * @var Array
	 * @access private
	 */
	var $mcrypt = array('', '');

	/**
	 * The Encryption Algorithm
	 *
	 * Only used if CRYPT_RC4_MODE == CRYPT_RC4_MODE_MCRYPT.  Only possible values are MCRYPT_RC4 or MCRYPT_ARCFOUR.
	 *
	 * @see rc4::__construct()
	 * @var Integer
	 * @access private
	 */
	var $mode;

	/**
	 * Default Constructor.
	 *
	 * Determines whether or not the mcrypt extension should be used.
	 *
	 * @param optional Integer $mode
	 * @return rc4
	 * @access public
	 */
	function __construct()
	{
		if ( !defined('CRYPT_RC4_MODE') )
		{
			switch (true)
			{
				case extension_loaded('mcrypt') && (defined('MCRYPT_ARCFOUR') || defined('MCRYPT_RC4')):
					// i'd check to see if rc4 was supported, by doing in_array('arcfour', mcrypt_list_algorithms('')),
					// but since that can be changed after the object has been created, there doesn't seem to be
					// a lot of point...
					define('CRYPT_RC4_MODE', CRYPT_RC4_MODE_MCRYPT);
					break;
				default:
					define('CRYPT_RC4_MODE', CRYPT_RC4_MODE_INTERNAL);
			}
		}

		switch ( CRYPT_RC4_MODE )
		{
			case CRYPT_RC4_MODE_MCRYPT:
				switch (true)
				{
					case defined('MCRYPT_ARCFOUR'):
						$this->mode = MCRYPT_ARCFOUR;
						break;
					case defined('MCRYPT_RC4');
						$this->mode = MCRYPT_RC4;
				}
		}
	}

	/**
	 * Sets the key.
	 *
	 * Keys can be between 1 and 256 bytes long.  If they are longer then 256 bytes, the first 256 bytes will
	 * be used.  If no key is explicitly set, it'll be assumed to be a single null byte.
	 *
	 * @access public
	 * @param String $key
	 */
	function set_key($key)
	{
		$this->key = $key;

		if (CRYPT_RC4_MODE == CRYPT_RC4_MODE_MCRYPT)
		{
			return;
		}

		$keyLength = strlen($key);
		$keyStream = array();
		for ($i = 0; $i < 256; $i++)
		{
			$keyStream[$i] = $i;
		}
		$j = 0;
		for ($i = 0; $i < 256; $i++)
		{
			$j = ($j + $keyStream[$i] + ord($key[$i % $keyLength])) & 255;
			$temp = $keyStream[$i];
			$keyStream[$i] = $keyStream[$j];
			$keyStream[$j] = $temp;
		}

		$this->encryptIndex = $this->decryptIndex = array(0, 0);
		$this->encryptStream = $this->decryptStream = $keyStream;
	}

	/**
	 * Dummy function.
	 *
	 * Some protocols, such as WEP, prepend an "initialization vector" to the key, effectively creating a new key [1].
	 * If you need to use an initialization vector in this manner, feel free to prepend it to the key, yourself, before
	 * calling set_key().
	 *
	 * [1] WEP's initialization vectors (IV's) are used in a somewhat insecure way.  Since, in that protocol,
	 * the IV's are relatively easy to predict, an attack described by
	 * {@link http://www.drizzle.com/~aboba/IEEE/rc4_ksaproc.pdf Scott Fluhrer, Itsik Mantin, and Adi Shamir}
	 * can be used to quickly guess at the rest of the key.  The following links elaborate:
	 *
	 * {@link http://www.rsa.com/rsalabs/node.asp?id=2009 http://www.rsa.com/rsalabs/node.asp?id=2009}
	 * {@link http://en.wikipedia.org/wiki/Related_key_attack http://en.wikipedia.org/wiki/Related_key_attack}
	 *
	 * @param String $iv
	 * @see rc4::set_key()
	 * @access public
	 */
	function setIV($iv)
	{
	}

	/**
	 * Sets MCrypt parameters. (optional)
	 *
	 * If MCrypt is being used, empty strings will be used, unless otherwise specified.
	 *
	 * @link http://php.net/function.mcrypt-module-open#function.mcrypt-module-open
	 * @access public
	 * @param optional Integer $algorithm_directory
	 * @param optional Integer $mode_directory
	 */
	function set_mcrypt($algorithm_directory = '', $mode_directory = '')
	{
		if ( CRYPT_RC4_MODE == CRYPT_RC4_MODE_MCRYPT )
		{
			$this->mcrypt = array($algorithm_directory, $mode_directory);
			$this->_close_mcrypt();
		}
	}

	/**
	 * Encrypts a message.
	 *
	 * @see rc4::_crypt()
	 * @access public
	 * @param String $plaintext
	 */
	function encrypt($plaintext)
	{
		return $this->_crypt($plaintext, CRYPT_RC4_ENCRYPT);
	}

	/**
	 * Decrypts a message.
	 *
	 * $this->decrypt($this->encrypt($plaintext)) == $this->encrypt($this->encrypt($plaintext)).
	 * Atleast if the continuous buffer is disabled.
	 *
	 * @see rc4::_crypt()
	 * @access public
	 * @param String $ciphertext
	 */
	function decrypt($ciphertext)
	{
		return $this->_crypt($ciphertext, CRYPT_RC4_DECRYPT);
	}

	/**
	 * Encrypts or decrypts a message.
	 *
	 * @see rc4::encrypt()
	 * @see rc4::decrypt()
	 * @access private
	 * @param String $text
	 * @param Integer $mode
	 */
	function _crypt($text, $mode)
	{
		if ( CRYPT_RC4_MODE == CRYPT_RC4_MODE_MCRYPT )
		{
			$keyStream = $mode == CRYPT_RC4_ENCRYPT ? 'encryptStream' : 'decryptStream';

			if ($this->$keyStream === false)
			{
				$this->$keyStream = mcrypt_module_open($this->mode, $this->mcrypt[0], MCRYPT_MODE_STREAM, $this->mcrypt[1]);
				mcrypt_generic_init($this->$keyStream, $this->key, '');
			}
			else if (!$this->continuousBuffer)
			{
				mcrypt_generic_init($this->$keyStream, $this->key, '');
			}
			$newText = mcrypt_generic($this->$keyStream, $text);
			if (!$this->continuousBuffer)
			{
				mcrypt_generic_deinit($this->$keyStream);
			}

			return $newText;
		}

		if ($this->encryptStream === false)
		{
			$this->set_key($this->key);
		}

		switch ($mode)
		{
			case CRYPT_RC4_ENCRYPT:
				$keyStream = $this->encryptStream;
				list($i, $j) = $this->encryptIndex;
				break;
			case CRYPT_RC4_DECRYPT:
				$keyStream = $this->decryptStream;
				list($i, $j) = $this->decryptIndex;
		}

		$newText = '';
		for ($k = 0; $k < strlen($text); $k++)
		{
			$i = ($i + 1) & 255;
			$j = ($j + $keyStream[$i]) & 255;
			$temp = $keyStream[$i];
			$keyStream[$i] = $keyStream[$j];
			$keyStream[$j] = $temp;
			$temp = $keyStream[($keyStream[$i] + $keyStream[$j]) & 255];
			$newText.= chr(ord($text[$k]) ^ $temp);
		}

		if ($this->continuousBuffer)
		{
			switch ($mode)
			{
				case CRYPT_RC4_ENCRYPT:
					$this->encryptStream = $keyStream;
					$this->encryptIndex = array($i, $j);
					break;
				case CRYPT_RC4_DECRYPT:
					$this->decryptStream = $keyStream;
					$this->decryptIndex = array($i, $j);
			}
		}

		return $newText;
	}

	/**
	 * Treat consecutive "packets" as if they are a continuous buffer.
	 *
	 * Say you have a 16-byte plaintext $plaintext.  Using the default behavior, the two following code snippets
	 * will yield different outputs:
	 *
	 * <code>
	 *	echo $rc4->encrypt(substr($plaintext, 0, 8));
	 *	echo $rc4->encrypt(substr($plaintext, 8, 8));
	 * </code>
	 * <code>
	 *	echo $rc4->encrypt($plaintext);
	 * </code>
	 *
	 * The solution is to enable the continuous buffer.  Although this will resolve the above discrepancy, it creates
	 * another, as demonstrated with the following:
	 *
	 * <code>
	 *	$rc4->encrypt(substr($plaintext, 0, 8));
	 *	echo $rc4->decrypt($des->encrypt(substr($plaintext, 8, 8)));
	 * </code>
	 * <code>
	 *	echo $rc4->decrypt($des->encrypt(substr($plaintext, 8, 8)));
	 * </code>
	 *
	 * With the continuous buffer disabled, these would yield the same output.  With it enabled, they yield different
	 * outputs.  The reason is due to the fact that the initialization vector's change after every encryption /
	 * decryption round when the continuous buffer is enabled.  When it's disabled, they remain constant.
	 *
	 * Put another way, when the continuous buffer is enabled, the state of the Crypt_DES() object changes after each
	 * encryption / decryption round, whereas otherwise, it'd remain constant.  For this reason, it's recommended that
	 * continuous buffers not be used.  They do offer better security and are, in fact, sometimes required (SSH uses them),
	 * however, they are also less intuitive and more likely to cause you problems.
	 *
	 * @see rc4::disable_continuous_buffer()
	 * @access public
	 */
	function enable_continuous_buffer()
	{
		$this->continuousBuffer = true;
	}

	/**
	 * Treat consecutive packets as if they are a discontinuous buffer.
	 *
	 * The default behavior.
	 *
	 * @see rc4::enable_continuous_buffer()
	 * @access public
	 */
	function disable_continuous_buffer()
	{
		if ( CRYPT_RC4_MODE == CRYPT_RC4_MODE_INTERNAL )
		{
			$this->encryptIndex = $this->decryptIndex = array(0, 0);
			$this->set_key($this->key);
		}

		$this->continuousBuffer = false;
	}

	/**
	 * Dummy function.
	 *
	 * Since RC4 is a stream cipher and not a block cipher, no padding is necessary.  The only reason this function is
	 * included is so that you can switch between a block cipher and a stream cipher transparently.
	 *
	 * @see rc4::disable_padding()
	 * @access public
	 */
	function enable_padding()
	{
	}

	/**
	 * Dummy function.
	 *
	 * @see rc4::enable_padding()
	 * @access public
	 */
	function disable_padding()
	{
	}

	/**
	 * Class destructor.
	 *
	 * Will be called, automatically, if you're using PHP5.  If you're using PHP4, call it yourself.  Only really
	 * needs to be called if mcrypt is being used.
	 *
	 * @access public
	 */
	function __destruct()
	{
		if ( CRYPT_RC4_MODE == CRYPT_RC4_MODE_MCRYPT )
		{
			$this->_close_mcrypt();
		}
	}

	/**
	 * Properly close the MCrypt objects.
	 *
	 * @access prviate
	 */
	function _close_mcrypt()
	{
		if ( $this->encryptStream !== false )
		{
			if ( $this->continuousBuffer )
			{
				mcrypt_generic_deinit($this->encryptStream);
			}

			mcrypt_module_close($this->encryptStream);

			$this->encryptStream = false;
		}

		if ( $this->decryptStream !== false )
		{
			if ( $this->continuousBuffer )
			{
				mcrypt_generic_deinit($this->decryptStream);
			}

			mcrypt_module_close($this->decryptStream);

			$this->decryptStream = false;
		}
	}
}