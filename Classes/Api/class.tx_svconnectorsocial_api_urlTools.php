<?php
	/***************************************************************
	*  Copyright notice
	*
	*  (c) 2011 Sy Moen <tech@gallupcurrent.com>
	*  All rights reserved
	*
	*  This script is part of the TYPO3 project, but is licenced
	*  under the MIT licence: http://en.wikipedia.org/wiki/MIT_License
	*  My name does not fall under this license, don't use it except
	*  to assert the copyright.
	*
	*  The TYPO3 project is
	*  free software; you can redistribute it and/or modify
	*  it under the terms of the GNU General Public License as published by
	*  the Free Software Foundation; either version 2 of the License, or
	*  (at your option) any later version.
	*
	*  The GNU General Public License can be found at
	*  http://www.gnu.org/copyleft/gpl.html.
	*
	*  This script is distributed in the hope that it will be useful,
	*  but WITHOUT ANY WARRANTY; without even the implied warranty of
	*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	*  GNU General Public License for more details.
	*
	*  This copyright notice MUST APPEAR in all copies of the script!
	***************************************************************/

class tx_svconnectorsocial_api_urlTools {

	public $prefixId = 'tx_svconnectorsocial_api_urlTools';				// Same as class name
	public $scriptRelPath = 'Classes/Api/class.tx_svconnectorsocial_api_urlTools.php';	// Path to this script relative to the extension dir.
	public $extKey = 'svconnector_social';						// The extension key.
	public $classVersion = '0.1.5';

	protected $origText;							// holds the original string
	protected $strippedText;							// string stripped of all urls
	protected $urls;								// array of all valid, cleaned urls 
	protected $expandedUrls;							// array of above urls expanded by longurl.com

	public function setOrigText($text) {
		if(!$text || $text == '') throw new Exception('No text was passed to setOrigText()', 6907250775);
		$this->origText = $text;
		$urls = $this->harvestUrls($text);
		$this->strippedText = array_shift($urls);
//		if ($this->extConf['debug'] || TYPO3_DLOG) t3lib_div::devLog('strippedText: ' . $this->strippedText, $this->extKey, 2, array($urls, $record, $index, $params)); 
		$this->urls = $urls;
	}

	public function getOrigText() {
		return $this->origText;
	}

	public function getStrippedText() {
		return $this->strippedText;
	}

	public function getUrls() {
		if(count($this->urls) > 0) return $this->urls;
		else throw new Exception('No Urls Exist', 6907250780);
	}

	protected function getUserAgent() {
		return 'Mozilla/5.0 (compatible; TYPO3 EXT:' . $this->prefixId . ' v' . $this->classVersion . ')';
	}

	protected function getHttpHeaderParts() {
		return array('User-Agent: ' . $this->getUserAgent);
	}


	public function removeUrl($url) {
		if(isset($this->origText) && is_array($this->urls) && (count($this->urls) > 0)) {
			if( ($key = array_search($url, $this->urls)) || ( is_array($this->expandedUrls) && ($key = array_search($url, $this->expandedUrls)) ) ) {
				unset($this->urls[$key]);
				if(is_array($this->expandedUrls)) unset($this->expandedUrls[$key]);
				return TRUE;
			}
			return array('error', 'url did not exist');
		}
		return array('error', 'no urls exist');
	}

	public function countUrls($expanded = false) {
		if($expanded) return count($this->expandedUrls);
		return count($this->urls);
	}

	/**
	 * Attempts to expand any shortened urls in the $this->urls var
	 * ??? should make this a param option of the getUrls function?
	 *
	 * @return array of expanded urls
	 * @throws Exception if longurls  call fails 
	 */
	public function getExpandedUrls() {
		if(count($this->urls) != count($this->expandedUrls)) {
			foreach($this->urls as $url) {
				$expandedUrls[] = $this->expandUrl($url);
			}
			$this->expandedUrls = $expandedUrls;
		}
		return $this->expandedUrls;
	}

	/**
	 * Attempts to retrive the oembed arrays of the urls passed in
	 *
	 * @param string $embedlyKey if you want to use embed.ly, you have to pass in the key
	 * @param string $urlParams embed.ly uses a number of optional url params (http://embed.ly/docs/endpoints/arguments),
	 *				see downloadOembeds()in this class  for options.
	 * @return array of oembed data sets
	 * @throws Exception (for now) if the embed.ly key is missing, Exception if embed.ly call fails 
	 */
	public function getOembeds($embedlyKey = '', $urlParams = '') {
		if(isset($this->origText) && (count($this->urls) > 0)) {
			if(!$embedlyKey) {
				// TODO look for oembed meta tag in page header, get oembed if available
//				if(count($this->urls) != count($this->expandedUrls)) $this->getExpandedUrls();
				throw new Exception('Embedly key not set', 6907250778);
			}
			try {
				if(is_array($urlParams) && count($urlParams) > 0)
					$oembedArray = $this->downloadOembed($this->urls, $embedlyKey, $urlParams);
				else
					$oembedArray = $this->downloadOembed($this->urls, $embedlyKey);
				// for cache tagging purposes, return all url varients with oembed
				for($i = 0; $i < count($this->urls); $i++) {
					if(is_array($oembedArray[$i])) $oembedArray[$i] = $oembedArray[$i] + array('orig' => $this->urls[$i]);
					else {
						$oembedArray[$i] = array('type' =>'error', 'report' => $oembedArray[$i], 'orig' => $this->urls[$i]);
						if (TYPO3_DLOG || $this->extConf['debug']) t3lib_div::devLog('Some kind of embed.ly error', $this->extKey, 3, array($oembedArray[$i], $this->urls));        
					}		
				}
				return $oembedArray;
			} catch (Exception $e) {
				throw $e;
			}
		}
		else
			throw new Exception('No urls (' . count($this->urls) . implode('  -  ', $this->urls) . ') or no text (' . $this->origText . ') available', 6907250779);
	}

	/**
	 * Return an array of validated urls extracted from a string.
	 *
	 * @param	string	$string: any text that might have urls in it.
	 * @return	array	array of validated urls or string stripped of urls, and origianl string stripped of urls at array[0]
	 *
	 */

	
	public function harvestUrls($string, $part = 'urls') {
		if(strlen($string) == 0) throw new Exception('Nothing to parse', 6907250774);
		$words = explode(' ', $string);
		$urls = array();	// if no urls found, return empty array
		foreach ($words as $key => $word) {
			if (stristr($word,".")) { //only preg_match if there is a dot
				$trimmed = trim($word, '!"#$%&\'()*+,-./@:;<=>[\\]^_`{|}~'); // http://mathiasbynens.be/demo/url-regex trim weird stuf from beginning and ending of urls
				if ($url = $this->validateUrl($trimmed)) {
					$urls[] = $url['url'];
				}
				else $text[] = $word;
			}
			else $text[] = $word;
		}
		array_unshift($urls, implode(' ', $text));
//		if (TYPO3_DLOG || $this->extConf['debug']) t3lib_div::devLog('Output of harvestUrls()', $this->extKey, 1, $urls);        
		return $urls;
	}
	

	/**
	 * function validateUrl($url) { Rev:20110423_2000
	 *
	 * Return associative array of valid URI components, or FALSE if $url is not
	 * RFC-3986 compliant. If the passed URL begins with: "www." or "ftp.", then
	 * "http://" or "ftp://" is prepended and the corrected full-url is stored in
	 * the return array with a key name "url". This value should be used by the caller.
	 *
	 * @author Jeff Roberson, "ridgerunner" on StackOverflow.com
	 * @ref http://jmrware.com/articles/2009/uri_regexp/URI_regex.html,
	 * @ref: http://stackoverflow.com/questions/161738/what-is-the-best-regular-expression-to-check-if-a-string-is-a-valid-url#5268056
	 *
	 * Return value: FALSE if $url is not valid, otherwise array of URI components:
	 * e.g.
	 * Given: "http://www.jmrware.com:80/articles?height=10&width=75#fragone"
	 * Array(
	 *	[scheme] => http
	 *	[authority] => www.jmrware.com:80
	 *	[userinfo] =>
	 *	[host] => www.jmrware.com
	 *	[IP_literal] =>
	 *	[IPV6address] =>
	 *	[ls32] =>
	 *	[IPvFuture] =>
	 *	[IPv4address] =>
	 *	[regname] => www.jmrware.com
	 *	[port] => 80
	 *	[path_abempty] => /articles
	 *	[query] => height=10&width=75
	 *	[fragment] => fragone
	 *	[url] => http://www.jmrware.com:80/articles?height=10&width=75#fragone
	 * )
	 *
	 * @param	string	$url: probable url
	 * @return	array	See Above
	 */

	public function validateUrl($url) {
		if (strpos($url, 'ftp.') === 0) $url = 'ftp://'. $url;
		elseif (!strpos($url, 'http') === 0) $url = 'http://'. $url;
		if (!preg_match('/# Valid absolute URI having a non-empty, valid DNS host.
			^
			(?P<scheme>[A-Za-z][A-Za-z0-9+\-.]*):\/\/
			(?P<authority>
				(?:(?P<userinfo>(?:[A-Za-z0-9\-._~!$&\'()*+,;=:]|%[0-9A-Fa-f]{2})*)@)?
				(?P<host>
					(?P<IP_literal>
						\[
						(?:
						(?P<IPV6address>
							(?:                                                (?:[0-9A-Fa-f]{1,4}:){6}
							|                                                ::(?:[0-9A-Fa-f]{1,4}:){5}
							| (?:                          [0-9A-Fa-f]{1,4})?::(?:[0-9A-Fa-f]{1,4}:){4}
							| (?:(?:[0-9A-Fa-f]{1,4}:){0,1}[0-9A-Fa-f]{1,4})?::(?:[0-9A-Fa-f]{1,4}:){3}
							| (?:(?:[0-9A-Fa-f]{1,4}:){0,2}[0-9A-Fa-f]{1,4})?::(?:[0-9A-Fa-f]{1,4}:){2}
							| (?:(?:[0-9A-Fa-f]{1,4}:){0,3}[0-9A-Fa-f]{1,4})?::   [0-9A-Fa-f]{1,4}:
							| (?:(?:[0-9A-Fa-f]{1,4}:){0,4}[0-9A-Fa-f]{1,4})?::
							)
							(?P<ls32>[0-9A-Fa-f]{1,4}:[0-9A-Fa-f]{1,4}
							| (?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}
							     (?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)
							)
							|   (?:(?:[0-9A-Fa-f]{1,4}:){0,5}[0-9A-Fa-f]{1,4})?::   [0-9A-Fa-f]{1,4}
							|   (?:(?:[0-9A-Fa-f]{1,4}:){0,6}[0-9A-Fa-f]{1,4})?::
						)
						| (?P<IPvFuture>[Vv][0-9A-Fa-f]+\.[A-Za-z0-9\-._~!$&\'()*+,;=:]+)
						)
						\]
					)
					| (?P<IPv4address>(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}
							     (?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?))
					| (?P<regname>(?:[A-Za-z0-9\-._~!$&\'()*+,;=]|%[0-9A-Fa-f]{2})+)
				)
				(?::(?P<port>[0-9]*))?
			)
			(?P<path_abempty>(?:\/(?:[A-Za-z0-9\-._~!$&\'()*+,;=:@]|%[0-9A-Fa-f]{2})*)*)
			(?:\?(?P<query>       (?:[A-Za-z0-9\-._~!$&\'()*+,;=:@\\/?]|%[0-9A-Fa-f]{2})*))?
			(?:\#(?P<fragment>    (?:[A-Za-z0-9\-._~!$&\'()*+,;=:@\\/?]|%[0-9A-Fa-f]{2})*))?
			$
			/mx', $url, $m)) return FALSE;
		switch ($m['scheme']) {
		case 'https':
		case 'http':
			if ($m['userinfo']) return FALSE; // HTTP scheme does not allow userinfo.
			break;
		case 'ftps':
		case 'ftp':
			break;
		default:
			return FALSE; // Unrecognized URI scheme. Default to FALSE.
		}
		// Validate host name conforms to DNS "dot-separated-parts".
		if ($m['regname']) { // If host regname specified, check for DNS conformance.
			if (!preg_match('/# HTTP DNS host name.
				^			# Anchor to beginning of string.
				(?!.{256})		# Overall host length is less than 256 chars.
				(?:			# Group dot separated host part alternatives.
					[A-Za-z0-9]\.	# Either a single alphanum followed by dot
				|			# or... part has more than one char (63 chars max).
					[A-Za-z0-9]	# Part first char is alphanum (no dash).
					[A-Za-z0-9\-]{0,61}	# Internal chars are alphanum plus dash.
					[A-Za-z0-9]	# Part last char is alphanum (no dash).
					\.		# Each part followed by literal dot.
				)*			# Zero or more parts before top level domain.
				(?:			# Explicitly specify top level domains.
					com|edu|gov|int|mil|net|org|biz|
					info|name|pro|aero|coop|museum|
					asia|cat|jobs|mobi|tel|travel|
				[A-Za-z]{2})		# Country codes are exactly two alpha chars.
				\.?			# Top level domain can end in a dot.
				$			# Anchor to end of string.
				/ix', $m['host'])) return FALSE;
		}
		$m['url'] = $url;
		for ($i = 0; isset($m[$i]); ++$i) unset($m[$i]);
		return $m; // return TRUE == array of useful named $matches plus the valid $url.
	}

	/**
	 * Returns the oembed information from a page.

	 * @param	array	$urls: url array to request oembed info for. A single value must be sent as an array as well.
	 * @param	string	$embedlyKey: this is the auth key you get in your account at embed.ly
	 * @param	array	$urlParams: array(
					'maxwidth'	=> 'int_val',	// maximum image width
					'maxheight'	=> 'int_val',	// max image height
					'width'		=> 'int_val',	// scale vid or rich to EXACT width... can cause distortion...
					'wmode'		=> 'window, opaque or transparent',
					'nostyle'	=> 'true or false',	// kill styles on some embeds
					'autoplay'	=> 'true or false',
					'words'		=> 'int_val',	// about how many words you want returned in the desc... it'll be close.
					'chars'		=> 'int_val'	// absolute number of characters in a desc... 'chars' takes prescedence over 'words'
					)
				more detail: http://embed.ly/docs/endpoints/arguments
	 * @return	array	[{"url":"url-encoded string", "caption":"html-encoded string", "alt":"html-encoded string", "title":"html-encoded string"}]
	 */
	protected function downloadOembed($urls, $embedlyKey='', $urlParams = array('maxwidth' => '500', 'chars' => '200')) {
		$report = array();
		$embedlyUrlBase = 'http://api.embed.ly/1/oembed?';
		if(!$embedlyKey) {
			// TODO Here we should check to see whether the url has oembed tags in the header:
			// or is in a known oembed list... we could then *not* use embed.ly, and thus reduce fees
			throw new Exception('Embedly key not set: ', 6907250772);
		}
		$embedlyUrlParams['key'] = $embedlyKey;
		$embedlyUrlParams['format'] = 'json';

// I thought embed.ly wanted the urls urlencoded... but when I urlencode, it gives an error. This could be a problem in the future, thus the optional code blocks.
//		if(count($urls) == 1) $embedlyUrlParams['url'] = urlencode($urls[0]);
		if(count($urls) == 1) $embedlyUrlParams['url'] = $urls[0];
		else {
/*			foreach($urls as $url) {
				$encodedUrls[] = urlencode($url);
			} */
//			$embedlyUrlParams['urls'] = implode(',', $encodedUrls);
			$embedlyUrlParams['urls'] = implode(',', $urls);
		}
		$embedlyUrlParams = $this->urlParamString(array_merge($embedlyUrlParams, $urlParams));
		$oembedDataPage = t3lib_div::getURL($embedlyUrlBase . $embedlyUrlParams, 0, $this->getHttpHeaderParts, $report);
		if(!$oembedDataPage) {
			if (TYPO3_DLOG || $this->extConf['debug']) t3lib_div::devLog('Something went wrong at embed.ly. Sent url: ' . $embedlyUrlBase . $embedlyUrlParams, $this->extKey, 3, array('getUrl report' => $report));
			throw new Exception('Page error at embed.ly, check dlog', 6907250773);
		}
		if($json = json_decode($oembedDataPage, true)) $oembeds = $json;
		else { // some syntax errors at embed.ly don't return as json even if json is specified... duh.
			if (TYPO3_DLOG || $this->extConf['debug']) t3lib_div::devLog('Embed.ly syntax error', $this->extKey, 3, array('embed.ly api call' => $embedlyUrlBase . $embedlyUrlParams,'embedly syntax error' => $oembedDataPage)); 
			throw new Exception('Embedly api syntax error. See dlog', 6907250790);
		}
		// embed.ly will not return an associative array for single oembeds, so, wrap the array in an array to make all returns the same type
		return (count($urls) == 1) ? array($oembeds) : $oembeds;
	}
	/**
	 * Merges an array of keys and values into a url param string without the leading '?'
	 *
	 * @param	array	$params: param set
	 * @return	string Parameter set for a url.
	 */
	public function urlParamString($params, $urlencode = FALSE) {
		foreach($params as $key => $value) {
			if(is_array($value)) continue;
			$paramArray[] = ($urlencode ? urlencode($key) : $key) . '=' . ($urlencode ? urlencode($value) : $value);
		}
		return implode('&', $paramArray);
	}

	/**
	 * Looks at link, determines if it is "shortened," if so, attempts to expand it.
	 *
	 * @param	array	$link: url to match against typically shortened set
	 * @param	bool	$longurl: if true, attempt to use the longurl.org service first, if flase, just use HTTP header analysis
	 * @param	bool	$force: if true, even if a url does not match a known shortener, still check for a redirect
	 * @return	string The url at the end of the redirect tunnel.
	 */
	public function expandUrl($link, $longurl = true, $force = false) {
		// this list from here: http://api.longurl.org/v2/services
		// TODO: getURL the xml, parse it into the regex, and cache the regex, then just use that until it expires
		$urlShorteners = array('0rz.tw', '1link.in', '1url.com', '2.gp', '2big.at', '2tu.us', '3.ly', '307.to', '4ms.me', '4sq.com', '4url.cc', '6url.com', '7.ly', 
		'a.gg', 'a.nf', 'aa.cx', 'abcurl.net', 'ad.vu', 'adf.ly', 'adjix.com', 'afx.cc', 'all.fuseurl.com', 'alturl.com', 'amzn.to', 'ar.gy', 'arst.ch', 'atu.ca', 'azc.cc',
		'b23.ru', 'b2l.me', 'bacn.me', 'bcool.bz', 'binged.it', 'bit.ly', 'bizj.us', 'bloat.me', 'bravo.ly', 'bsa.ly', 'budurl.com',
		'canurl.com', 'chilp.it', 'chzb.gr', 'cl.lk', 'cl.ly', 'clck.ru', 'cli.gs', 'cliccami.info', 'clickthru.ca', 'clop.in', 'conta.cc', 'cort.as', 'cot.ag', 'crks.me', 'ctvr.us', 'cutt.us',
		'dai.ly', 'decenturl.com', 'dfl8.me', 'digbig.com', 'digg.com', 'disq.us', 'dld.bz', 'dlvr.it', 'do.my', 'doiop.com', 'dopen.us',
		'easyuri.com', 'easyurl.net', 'eepurl.com', 'eweri.com',
		'fa.by', 'fav.me', 'fb.me', 'fbshare.me', 'ff.im', 'fff.to', 'fire.to', 'firsturl.de', 'firsturl.net', 'flic.kr', 'flq.us', 'fly2.ws', 'fon.gs', 'freak.to', 'fuseurl.com', 'fuzzy.to', 'fwd4.me', 'fwib.net',
		'g.ro.lt', 'gizmo.do', 'gl.am', 'go.9nl.com', 'go.usa.gov', 'goo.gl', 'goshrink.com', 'gurl.es',
		'hex.io', 'hiderefer.com', 'hmm.ph', 'href.in', 'hsblinks.com', 'htxt.it', 'huff.to', 'hulu.com', 'hurl.me', 'hurl.ws',
		'icanhaz.com', 'idek.net', 'ilix.in', 'is.gd', 'its.my', 'ix.lt', 'j.mp', 'jijr.com', 'kl.am', 'klck.me', 'korta.nu', 'krunchd.com',
		'l9k.net', 'lat.ms', 'liip.to', 'liltext.com', 'linkbee.com', 'linkbun.ch', 'liurl.cn', 'ln-s.net', 'ln-s.ru', 'lnk.gd', 'lnk.ms', 'lnkd.in', 'lnkurl.com', 'lru.jp', 'lt.tl', 'lurl.no',
		'macte.ch', 'mash.to', 'merky.de', 'migre.me', 'miniurl.com', 'minurl.fr', 'mke.me', 'moby.to', 'moourl.com', 'mrte.ch', 'myloc.me', 'myurl.in',
		'n.pr', 'nbc.co', 'nblo.gs', 'nn.nf', 'not.my', 'notlong.com', 'nsfw.in', 'nutshellurl.com', 'nxy.in', 'nyti.ms',
		'o-x.fr', 'oc1.us', 'om.ly', 'omf.gd', 'omoikane.net', 'on.cnn.com', 'on.mktw.net', 'orz.se', 'ow.ly',
		'ping.fm', 'pli.gs', 'pnt.me', 'politi.co', 'post.ly', 'pp.gg', 'profile.to', 'ptiturl.com', 'pub.vitrue.com', 'qlnk.net', 'qte.me', 'qu.tc',
		'r.im', 'rb6.me', 'read.bi', 'readthis.ca', 'reallytinyurl.com', 'redir.ec', 'redirects.ca', 'redirx.com', 'retwt.me', 'ri.ms', 'rickroll.it', 'riz.gd', 'rt.nu', 'ru.ly', 'rubyurl.com', 'rurl.org', 'rww.tw',
		's4c.in', 's7y.us', 'safe.mn', 'sdut.us', 'shar.es', 'shink.de', 'shorl.com', 'short.ie', 'short.to', 'shortlinks.co.uk', 'shorturl.com', 'shout.to', 'show.my', 'shrinkify.com', 'shrinkr.com', 'shrt.fr', 'shrt.st', 'shrten.com', 'shrunkin.com', 'simurl.com', 'slate.me', 'smallr.com', 'smsh.me', 'smurl.name', 'sn.im', 'snipr.com', 'snipurl.com', 'snurl.com', 'sp2.ro', 'spedr.com', 'srnk.net', 'srs.li', 'starturl.com', 'su.pr', 'surl.co.uk', 'surl.hu',
		't.co', 't.lh.com', 'ta.gd', 'tbd.ly', 'tcrn.ch', 'tgr.me', 'tgr.ph', 'tighturl.com', 'tiniuri.com', 'tiny.cc', 'tiny.ly', 'tiny.pl', 'tinylink.in', 'tinyuri.ca', 'tinyurl.com', 'tk.', 'tl.gd', 'tmi.me', 'tnij.org', 'tny.com', 'to.', 'to.ly', 'togoto.us', 'totc.us', 'toysr.us', 'tpm.ly', 'tr.im', 'tra.kz', 'trunc.it', 'twhub.com', 'twirl.at', 'twitclicks.com', 'twitterurl.net', 'twitterurl.org', 'twiturl.de', 'twurl.cc', 'twurl.nl',
		'u.mavrev.com', 'u.nu', 'u76.org', 'ub0.cc', 'ulu.lu', 'updating.me', 'ur1.ca', 'url.az', 'url.co.uk', 'url.ie', 'url360.me', 'url4.eu', 'urlborg.com', 'urlbrief.com', 'urlcover.com', 'urlcut.com', 'urlenco.de', 'urli.nl', 'urls.im', 'urlshorteningservicefortwitter.com', 'urlx.ie', 'urlzen.com', 'usat.ly', 'use.my', 
		'vb.ly', 'vgn.am', 'vl.am', 'w55.de', 'wapo.st', 'wapurl.co.uk', 'wipi.es', 'wp.me', 'x.vu', 'xr.com', 'xrl.in', 'xrl.us', 'xurl.es', 'xurl.jp',
		'y.ahoo.it', 'yatuc.com', 'ye.pe', 'yep.it', 'yfrog.com', 'yhoo.it', 'yiyd.com', 'youtu.be', 'yuarel.com',
		'z0p.de', 'zi.ma', 'zi.mu', 'zipmyurl.com', 'zud.me', 'zurl.ws', 'zz.gd', 'zzang.kr');

		// parse that array into a regex
		foreach($urlShorteners as $url) {
			$urls[] = array_reverse(explode('.', $url));
		}

		foreach($urls as $url) {
			$tldKeys[array_shift($url)][] = $url;
		}

		foreach($tldKeys as $tld => $doms) {
			if($tld != '') {
				$subPattern = array();
				foreach($doms as $subDomain) {
					$subPattern[] = implode("\.", array_reverse($subDomain));
				}
				if(count($subPattern) > 1) $optionPattern[] = "((?:" . implode("|", $subPattern) . ")\." . $tld . ")";
				else $optionPattern[] = "(" . $subPattern[0] . "\." . $tld . ")";
			}
		}
		$regex = '/' . implode('|', $optionPattern) . '/';

		// see if the url matches a known url shortener
		preg_match($regex, $link, $matches);
		if(isset($matches[0]) && $longurl) {
			$longUrlBase = 'http://api.longurl.org/v2/expand?';
//			$longUrlParams['url'] = urlencode($link);
			$longUrlParams['url'] = $link;
			$longUrlParams['content-type'] = '1';
			$longUrlParams['response-code'] = '1';
			$longUrlParams['format'] = 'json';
			$longUrl = $longUrlBase . $this->urlParamString($longUrlParams);
			$expandedUrlPage = t3lib_div::getURL($longUrl, 0, $this->getHttpHeaderParts, $report);
			if($responce = json_decode($expandedUrlPage, true))
				$expandedLink = stripslashes($responce['long-url']);
				if ($this->extConf['debug'] || TYPO3_DLOG) t3lib_div::devLog('Expanded Url comes from longurl ' . $link . ' => ' . $expandedLink, $this->extKey, 2, array($report, $expandedUrlPage, $longUrl));
			else {
//				throw new Exception('Something went wrong at longurl.org, check the dlog', 6907250780);
				$expandedLink = $this->getFinalUrl($link);
				if ($this->extConf['debug'] || TYPO3_DLOG) t3lib_div::devLog('Something went wrong at longurl.org, used getFinalUrl instead: ' . $link . ' => ' . $expandedLink, $this->extKey, 2, array($report, $expandedUrlPage, $longUrl));
			}
		} elseif(!isset($matches[0]) && $force) {
			$expandedLink = $this->getFinalUrl($link);
			if ($this->extConf['debug'] || TYPO3_DLOG) t3lib_div::devLog('No short url match, $force = TRUE, used getFinalUrl instead: ' . $link . ' => ' . $expandedLink, $this->extKey, 2);
		}
		return $expandedLink ? $expandedLink : $link;
	}

	/**
	 * Gets the address that the provided URL redirects to, or FALSE if there's no redirect. 
	 * @author Whiteshadow <whiteshadow[at]w-shadow[dot]com> Ref: http://w-shadow.com/blog/2008/07/05/how-to-get-redirect-url-in-php/
	 *
	 * @param string $url The given url to analyze
	 * @return string Last url in the redirect maze, or FALSE if no redirect
	 */
	protected function getRedirectUrl($url){
		$redirect_url = null; 
	 
		$url_parts = @parse_url($url);
		if (!$url_parts) return false;
		if (!isset($url_parts['host'])) return false; //can't process relative URLs
		if (!isset($url_parts['path'])) $url_parts['path'] = '/';
	 
		$sock = fsockopen($url_parts['host'], (isset($url_parts['port']) ? (int)$url_parts['port'] : 80), $errno, $errstr, 30);
		if (!$sock) return false;
	 
		$request = "HEAD " . $url_parts['path'] . (isset($url_parts['query']) ? '?'.$url_parts['query'] : '') . " HTTP/1.1\r\n"; 
		$request .= 'Host: ' . $url_parts['host'] . "\r\n"; 
		$request .= 'User-Agent: ' . $this->getUserAgent . "\r\n";
		$request .= "Connection: Close\r\n\r\n"; 
		fwrite($sock, $request);
		$response = '';
		while(!feof($sock)) $response .= fread($sock, 8192);
		fclose($sock);
	 
		if (preg_match('/^Location: (.+?)$/m', $response, $matches)){
			if ( substr($matches[1], 0, 1) == "/" )
				return $url_parts['scheme'] . "://" . $url_parts['host'] . trim($matches[1]);
			else
				return trim($matches[1]);
	 
		} else {
			return false;
		}
	 
	}
	 
	/**
	 * Follows and collects all redirects, in order, for the given URL. 
	 * @author Whiteshadow <whiteshadow[at]w-shadow[dot]com> Ref: http://w-shadow.com/blog/2008/07/05/how-to-get-redirect-url-in-php/
	 *
	 * @param string $url The given url to analyze
	 * @return array Entire list of redirects in order
	 */
	public function getAllRedirects($url){
		$redirects = array();
		while ($newurl = $this->getRedirectUrl($url)){
			if (in_array($newurl, $redirects)){
				break;
			}
			$redirects[] = $newurl;
			$url = $newurl;
		}
		return $redirects;
	}
	 
	/**
	 * Gets the address that the URL ultimately leads to. 
	 * Returns $url itself if it isn't a redirect.
	 * @author Whiteshadow <whiteshadow[at]w-shadow[dot]com> Ref: http://w-shadow.com/blog/2008/07/05/how-to-get-redirect-url-in-php/
	 *
	 * @param string $url The given url to analyze
	 * @return string Original url if no redirect, or final redirected destination url
	 */
	public function getFinalUrl($url){
		$redirects = $this->getAllRedirects($url);
		if (count($redirects)>0){
			return array_pop($redirects);
		} else {
			//TODO maybe cache domains or domain patterns that did not redirect to save efort in the future?
			return $url;
		}
	}

// TYPO3 realted subtasks

	/**
	 * returns a string that is a human readable valid TYPO3 cache tag from a url
	 *	eg: http://domain.com/really/long/url/string?more=that_just+keeps+going+and+going&howLongDoesItGo=and+going+and+going+and+going+for+like#upto-2000-chars
	 *	becomes: domain.com...everything+cut+out+except+for+the+last+part+creating+a+249+char+string
	 *
	 * @param	string	$url: the full record that is being transformed
	 * @return	string	$tag: string that conforms to this pattern: '/^[a-zA-Z0-9_%\-&]{1,250}$/'
	 */
	public function createValidTagFromUrl($url) {	
		if(!$url) return;
		if(strlen($url) > 250) {
			$parsedUrl = $this->validateUrl($url);
			$url = $parsedUrl['host'] . '...' . 
				substr(
					$parsedUrl['path_abempty']?$parsedUrl['path_abempty']:'' . 
					$parsedUrl['query']?('?' . $parsedUrl['query']):'' . 
					$parsedUrl['fragment']?('#' . $parsedUrl['fragment']):''
					, -245 + strlen($parsedUrl['host']), 0);
		}
		$tag = str_replace('.', '_', urlencode($url));
		return $tag;
	}
}
?>
