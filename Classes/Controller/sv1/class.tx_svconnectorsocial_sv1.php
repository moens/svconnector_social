<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Sy Moen <tech@gallupcurrent.com> & Francois Suter (Cobweb) <typo3@cobweb.ch>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
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
*
***************************************************************/

/**
 * Service that authenticates and reads XML or json feeds from social networks for the "svconnector_social" extension.
 *
 * @author		Francois Suter (Cobweb) <typo3@cobweb.ch>
 * @package		TYPO3
 * @subpackage	tx_svconnectorsocial
 */
class tx_svconnectorsocial_sv1 extends tx_svconnector_base {
	public $prefixId = 'tx_svconnectorsocial_sv1';		// Same as class name
	public $scriptRelPath = 'Classes/Controller/sv1/class.tx_svconnectorsocial_sv1.php';	// Path to this script relative to the extension dir.
	public $extKey = 'svconnector_social';	// The extension key.
	protected $extConf; // Extension configuration

	/**
	 * Verifies that the connection is functional
	 * In the case of this service, it is always the case
	 * It might fail for a specific file, but it is always available in general
	 *
	 * @return	boolean		TRUE if the service is available
	 */
	public function init() {
		parent::init();
		$this->lang->includeLLFile('EXT:' . $this->extKey . 'Classes/Controller/sv1/locallang.xml');
		$this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);
		$this->initCache();
		return true;
	}

	protected function initCache() {
		$version = class_exists('t3lib_utility_VersionNumber')
			? t3lib_utility_VersionNumber::convertVersionNumberToInteger(TYPO3_version)
			: t3lib_div::int_from_ver(TYPO3_version);
		if ( (($version < 4006000) && TYPO3_UseCachingFramework) || $version >= 4006000) {
			// Create the cache
			try {
				// create($cacheIdentifier, $cacheName, $backendName, array $backendOptions = array())
				$GLOBALS['typo3CacheFactory']->create(
					$this->extKey,
					't3lib_cache_frontend_VariableFrontend',
//					$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$this->extKey]['frontend'],
					$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$this->extKey]['backend'],
					$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$this->extKey]['options']
				);
			} catch(t3lib_cache_exception_DuplicateIdentifier $e) {
				// do nothing, the cache already exists
			}
			// create handle for cache
			try {
				$this->localCache = $GLOBALS['typo3CacheManager']->getCache($this->extKey);
			} catch(t3lib_cache_exception_NoSuchCache $e) {	
				if (TYPO3_DLOG || $this->extConf['debug']) t3lib_div::devLog('The Cache did not initialize, you may have rate limiting issues with social APIs',
					$this->extKey, 3, array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$this->extKey], $e->getMessage));
			}
		}
	}


	/**
	 * This method calls the query method and returns the result as is,
	 * i.e. the XML or JSON from the social feed, but without any additional work performed on it
	 *
	 * @param	array	$parameters: parameters for the call
	 * @return	mixed	server response
	 */
	public function fetchRaw($parameters) {
		$result = $this->query($parameters, 'raw');
			// Implement post-processing hook
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][$this->extKey]['processRaw'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][$this->extKey]['processRaw'] as $className) {
				$processor = &t3lib_div::getUserObj($className);
				$result = $processor->processRaw($result, $this);
			}
		}

		return $result;
	}

	/**
	 * This method calls the query and returns the results from the response as an XML structure
	 *
	 * @param	array	$parameters: parameters for the call
	 * @return	string	XML structure
	 */
	public function fetchXML($parameters) {
			// Get the social feed and have it returned as xml
		$xml = $this->query($parameters, 'xml');
			// Implement post-processing hook
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][$this->extKey]['processXML'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][$this->extKey]['processXML'] as $className) {
				$processor = &t3lib_div::getUserObj($className);
				$xml = $processor->processXML($xml, $this);
			}
		}

		return $xml;
	}

	/**
	 * This method calls the query and returns the results from the response as a PHP array
	 *
	 * @param	array	$parameters: parameters for the call
	 *
	 * @return	array	PHP array
	 */
	public function fetchArray($parameters) {
			// Get the data from the file
		$result = $this->query($parameters, 'raw');
		if($array = json_decode($result, true)) {
			$result = $array;
		} elseif($xmlObj = simplexml_load_string($result)) {
			$result = $result; // do nothing...
		} elseif($array = unserialize($result)) {
			if(is_array($array)) $result = $array;
			else $result = false;
		} else {
			$result = false;
		}

		if (TYPO3_DLOG || $this->extConf['debug']) {
			t3lib_div::devLog('Structured data', $this->extKey, -1, $result);
		}

		// Implement post-processing hook
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][$this->extKey]['processArray'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][$this->extKey]['processArray'] as $className) {
				$processor = &t3lib_div::getUserObj($className);
				$result = $processor->processArray($result, $this);
			}
		}
		return $result;
	}

	/**
	 * This method takes the data returned by the distant source as array and prepares it
	 * for update/insertion/deletion in the database
	 * Using the jsonpath method, it allows the array to be traversed much like xml using xmlpath.
	 * This helps greatly in parsing arrays converted from complex json objects
	 * --> be careful, when a recursive jpath is done, it will likely result in an array, sometimes a multi-dim
	 * array... in these cases, only the first value (eg: key => [key => value]) will be used. Use a well formed
	 * jpath expression to avoid random "first option" default nature.
	 *
	 * @param	array		$rawData: response array
	 * @return	array		response stored as an indexed array of records (associative array of fields)
	 */

	public function handleArray($rawData) {
		require_once(t3lib_extMgm::extPath($this->extKey, 'Classes/Api/jsonpath/jsonpath.php'));

		$data = array();
		if (is_array($rawData) && count($rawData) > 0) {
			
			// Get the nodes that represent the root of each data record as in handleXML()
			// see jsonPath syntax here: http://code.google.com/p/jsonpath/wiki/PHP
			// nothing special needs to be done for root level nodes, just leave nodetype blank
			$records = jsonPath($rawData, "$." . $this->externalConfig['nodetype'] . "[*]");
			if(count($records) > 0) {
				// Loop on all external data entities
				foreach ($records as $theRecord) {
					$theData = array();

					// Loop on the database columns and get the corresponding value from the import data
					foreach ($this->tableTCA['columns'] as $columnName => $columnData) {
						// Does this column have a "field" key?
						if (isset($columnData['external'][$this->index]['field'])) {
/*
							$nodeList = $theRecord->getElementsByTagName($columnData['external'][$this->index]['field']);
							if ($nodeList->length > 0) {


								$selectedNode = $nodeList->item(0);
*/
							// Does the external data entity have a value for this "field" value?
							if (isset($theRecord[$columnData['external'][$this->index]['field']])) {

// if ($this->extConf['debug'] || TYPO3_DLOG) t3lib_div::devLog('Does the external data entity have a value (' . $theRecord[$columnData['external'][$this->index]['field']] . ') for the "field" value (' . $columnData['external'][$this->index]['field'] .')? ', $this->extKey, 1, $theRecord);
								$theData[$columnName] = $theRecord[$columnData['external'][$this->index]['field']];
								// If it does, and there is a jsonpath expression defined as well, apply it (relative to currently selected node)
								if (!empty($columnData['external'][$this->index]['jsonpath'])) {
									$resultNodes = jsonPath($theRecord[$columnData['external'][$this->index]['field']], "$." . $columnData['external'][$this->index]['jsonpath'] . "[*]");
//															$xPathObject->evaluate($columnData['external'][$this->index]['xpath'], $selectedNode);
									if (count($resultNodes) > 0) {
										$theData[$columnName] = $resultNodes[0];
									}
								}
/* This portion makes sence in the xml section, but arrays don't have attributes, so all data is accessible
 * with the previous jsonpath section.
								// Get the named attribute, if defined
								if (!empty($columnData['external'][$this->index]['attribute'])) {
									$theData[$columnName] = $selectedNode->attributes->getNamedItem($columnData['external'][$this->index]['attribute'])->nodeValue;
		
									// Otherwise directly take the node's value
								} else {
									$theData[$columnName] = $selectedNode->nodeValue;
								}
*/
//								$theData[$columnName] = $selectedNode ? $selectedNode : $theRecord[$columnData['external'][$this->index]['field']];
							}
						}
					}
/*
					foreach ($this->tableTCA['columns'] as $columnName => $columnData) {
						if (isset($columnData['external'][$this->index]['field'])) {
							if (isset($theRecord[$columnData['external'][$this->index]['field']])) {
								$theData[$columnName] = $theRecord[$columnData['external'][$this->index]['field']];
							}
						}
					}
*/
					
					// Get additional fields data, if any
					if ($this->numAdditionalFields > 0) {
						foreach ($this->additionalFields as $fieldName) {
							if (isset($theRecord[$fieldName])) {
								$theData[$fieldName] = $theRecord[$fieldName];
							}
						}
					}
					$data[] = $theData;
				}
			}
		}
		return $data;
	}

	/**
	 * This method creates an authenticated URI for the social feed as needed
	 * method currently works for
	 * 	Twitter generic search (not authenticated),
	 * 	Facebook generic post or page search (not authenticated),
	 *	Flickr flickr.photos.search method (authenticated)
	 *
	 * @param	array	$parameters: parameters for the call
	 * @param	string	$notation: 'xml' or 'json' for the desired return notation of the API call
	 * @return	mixed	uri string for the desired social feed	
	 */
	protected function createUri($parameters, $notation = 'json') {

		switch ($parameters['network']) {
			case 'flickr':
				$uriBase = 'http://api.flickr.com/services/rest/?';
				if($notation == 'xml') $format = 'rest';
					else $format = 'json';

				$flickrSetupMessage = array();
				if(!$parameters['flickr_api_key']) $flickrSetupMessage[] = 'flickr_api_key';
				if(!$parameters['flickr_api_secret']) $flickrSetupMessage[] = 'flickr_api_secret';
				if(!$parameters['flickr_auth_token']) $flickrSetupMessage[] = 'flickr_auth_token';
				if(!$parameters['flickr_search_text'] && !$parameters['flickr_search_tags']) $flickrSetupMessage[] = $this->lang->getLL('either_the_flickr_search_text_or_the_flickr_search_tags');
				if(count($flickrSetupMessage) > 1) $message = $this->lang->getLL('to_setup_flickr_you_must_set') . implode(',', $flickrSearchMessage);

				if(!$message) {
					// TODO: this should be turned into a generic function to make this class work with any extension (not just tt_news)
					$query = $GLOBALS['TYPO3_DB']->prepare_SELECTquery('tx_social2news_external,crdate', 'tt_news', 'tx_social2news_external_source = :service', '', 'crdate DESC', '1');
					$query->execute(array(':service' => 'flickr'));
					$latest = $query->fetch();
					$query->free();
					$since = $latest['crdate'] + 1;

					//Extract arguments, including method and login data.
					if (preg_match_all('/[^=&\?]{1,}=[^&\?]{1,}/', $parameters['flickr_method_params'], $methodParams)) {
						foreach($methodParams[0] as $param) {
							list($key, $value) = explode('=', $param); 
							$args[$key] = $value;
						}
					}
					$methodParams = array();
					$args = array_merge($args, array(
//						'extras' => 'description, date_upload, icon_server, geo, tags, url_sq, url_t, url_s, url_m, url_z, url_l, url_o',
						'method' => 'flickr.photos.search',
						'api_key' => $parameters['flickr_api_key'],
						'auth_token' => $parameters['flickr_auth_token'],
						'min_upload_date' => $since,
						'format' => $format // 'rest' here for xml output, 'json' for json
					));
					if($parameters['flickr_contacts']) $args = array_merge($args, array('contacts' => $parameters['flickr_contacts']));
					if($parameters['flickr_search_tags']) $args = array_merge($args, array('tags' => $parameters['flickr_search_tags']));
					if($parameters['flickr_tag_mode'] && $parameters['flickr_search_tags']) $args = array_merge($args, array('tag_mode' => $parameters['flickr_tag_mode']));
					if($parameters['flickr_search_text']) $args = array_merge($args, array('tags' => $parameters['flickr_search_text']));
					ksort($args);
					$auth_sig = '';
					foreach ($args as $key => $value) {
						$auth_sig .= $key . $value;
						$methodParams[] = $key . '=' . $value;
					}
					$api_sig = md5($parameters['flickr_api_secret'] . $auth_sig);
					$methodParams[] = 'api_sig=' . $api_sig;

					
	/* TODO: put this in the calling function: special flickr error messages...
					if ($this->parsed_response['stat'] == 'fail') {
						if ($this->die_on_error) die("The Flickr API returned the following error: #{$this->parsed_response['code']} - {$this->parsed_response['message']}");
						else {
							$this->error_code = $this->parsed_response['code'];
							$this->error_msg = $this->parsed_response['message'];
							$this->parsed_response = false;
						}
					} else {
						$this->error_code = false;
						$this->error_msg = false;
					}
					return $this->response;
	*/
				}

				$uri = $uriBase . implode('&', $methodParams);
				break;
			case 'facebook':
				if($notation == 'xml') $notation = 'convertJsonToXml'; // no xml option, have to attempt to convert it after the fact

				// stuff from "I Love LiveGallup" see: http://www.facebook.com/developers/apps.php?app_id=187644384619883
				// $AppId =	'187644384619883';
				// $ApiKey =	'0ff597bcde2547459cc58d8b794a1741';
				// $AppSecret =	'37f5111aa32c28ef07024804fbb155ba';
				// Check to see if a valid token is available in the last stored Facebook news item... possible a new db table: token|user:token:api:authType
				
				$uriBase = 'https://graph.facebook.com/search?';
				if(!$parameters['facebook_search_text']) $message = $this->lang->getLL('no_facebook_search_text_defined');
				if(!$parameters['facebook_search_object']) $parameters['facebook_search_object'] = 'post';

				if(!$message) {
					// get the id of the last post that was turned into a tt_news article
					$query = $GLOBALS['TYPO3_DB']->prepare_SELECTquery('tx_social2news_external,crdate', 'tt_news', 'tx_social2news_external_source = :service', '', 'crdate DESC', '1');
					$query->execute(array(':service' => 'facebook'));
					$latest = $query->fetch();
					$query->free();
					$since = $latest['crdate'] + 1;

					$q = $parameters['facebook_search_text'];
					$uri = $uriBase . 'q=' . urlencode($q) . '&type=' . $parameters['facebook_search_object'] . '&date_format=U' . '&since=' . $since;
				}
				break;

			/**
			 * Posible params for a twitter query to the search api: http://dev.twitter.com/doc/get/search
			 *
			 * Required Param:
			 * q	Search query. Should be URL encoded. "gallup new mexico" OR gallupnm
			 *
			 * Optional params:
			 * callback	Only available for JSON format. If supplied, the response will use
			 *		the JSONP format with a callback of the given name.
			 * lang		Restricts tweets to the given language, given by an ISO 639-1 code.
			 *		http://en.wikipedia.org/wiki/List_of_ISO_639-1_codes eg, English = eng
			 * locale	Specify the language of the query you are sending (only ja is currently effective).
			 * rpp		The number of tweets to return per page, up to a max of 100.
			 * page		The page number (starting at 1) to return, up to a max of roughly
			 *		1500 results (based on rpp * page).
			 * since_id	Returns results with an ID greater than (that is, more recent than) the specified ID.
			 *		There are limits to the number of Tweets which can be accessed through the API.
			 *		If the limit has occured since the since_id, the since_id will be forced to the oldest ID available.
			 * until	Returns tweets generated before the given date. Date should be formatted as YYYY-MM-DD.
			 * geocode	Returns tweets by users located within a given radius of the given latitude/longitude. 
			 * 		The location is preferentially taken from the Geotagging API, but will fall back to their Twitter profile.
			 *		The parameter value is specified by "latitude,longitude,radius", where radius units must be specified as
			 *		either "mi" (miles) or "km" (kilometers). Note that you cannot use the near operator via the API to
			 *		geocode arbitrary locations; however you can use this geocode parameter to search near geocodes directly.
			 *		eg: geocode=37.781157,-122.398720,1mi
			 * show_user	When true, prepends ":" to the beginning of the tweet. This is useful for readers that do not display
			 *		Atom's author field. The default is false.
			 * result_type	Specifies what type of search results you would prefer to receive. The current default is "mixed."
			 *		Valid values include:
			 *		mixed: Include both popular and real time results in the response.
			 *		recent: return only the most recent results in the response
			 *		popular: return only the most popular results in the response.
			 */
			case 'twitter':
				$uriBase = 'http://search.twitter.com/search.';
				if($notation == 'xml') $uriBase .= 'atom?';
					else $uriBase .= 'json?';
				if(!$parameters['twitter_search_text']) $message = $this->lang->getLL('no_twitter_search_text_defined');

				if(!$message) {

					// get the id of the last tweet that was turned into a tt_news external link
					$query = $GLOBALS['TYPO3_DB']->prepare_SELECTquery('tx_social2news_external,crdate', 'tt_news', 'tx_social2news_external_source = :service', '', 'crdate DESC', '1');
					$query->execute(array(':service' => 'twitter'));
					$latestTweet = $query->fetch();
					$query->free();
					$since_id = $latestTweet['tx_social2news_external'];
					$result_type = 'recent';
					$q = $parameters['twitter_search_text'];
					$uri = $uriBase . 'q=' . urlencode($q) . '&result_type=' . $result_type . ($since_id?('&since_id='.$since_id):'');
				}
				break;

			default:
				$message = $this->lang->getLL('no_social_network_defined');
	
		}
		if($message) {
			if (TYPO3_DLOG || $this->extConf['debug']) {
				t3lib_div::devLog($message, $this->extKey, 3);
			}
			throw new Exception($message, 6907250762);
		}
		return array('uri' => $uri, 'notation' => $notation);
	}



	/**
	 * This method reads the content of the social feed defined in the parameters
	 * and returns it as an array
	 *
	 * NOTE:	this method does not implement the "processParameters" hook,
	 *			as it does not make sense in this case
	 *
	 * @param	array	$parameters: parameters for the call
	 * @param	string	$notation: xml or json... the notation desired from the api call
	 * @return	array	content of the social feed as xml or raw
	 */
	protected function query($parameters, $notation = 'json') {

		if (TYPO3_DLOG || $this->extConf['debug']) t3lib_div::devLog('Call parameters', $this->extKey, -1, $parameters);
		
		$uri = $this->createUri($parameters, $notation);

		if (TYPO3_DLOG || $this->extConf['debug']) t3lib_div::devLog('uri: ' . $uri['uri'], $this->extKey, 1, '');
		
	 		// Did we create the social uri from the given params?
		if (empty($uri['uri'])) {
			$message = $this->lang->getLL('no_social_network_defined');
			if (TYPO3_DLOG || $this->extConf['debug']) t3lib_div::devLog($message, $this->extKey, 3);
			throw new Exception($message, 1299257883);
		} else {
			$report = array();
	
			$urlTools = t3lib_div::getUserObj('EXT:svconnector_social/Classes/Api/class.tx_svconnectorsocial_api_urlTools.php:&tx_svconnectorsocial_api_urlTools');
			// check for cached version
			$tag = $urlTools->createValidTagFromUrl($uri['uri']);
			try {
				$cacheResult = $this->localCache->getByTag($tag);
			} catch(\InvalidArgumentException $e) {
				if ($this->extConf['debug'] || TYPO3_DLOG) t3lib_div::devLog('invalid tag (\'/^[a-zA-Z0-9_%\-&]{1,250}$/\'): ' . $tag . ' e->msg: ' . $e->getMessage(), $this->extKey, 2);
			}
			if($cacheResult) $data = $cacheResult[0];
	
			//otherwise, get remote data		
			else {
				$data = t3lib_div::getURL($uri['uri'], 0, FALSE, $report);

				if (!empty($report['message'])) {
					$message = sprintf($this->lang->getLL('social_feed_not_found'), $parameters['uri'], $report['message']);
					if (TYPO3_DLOG || $this->extConf['debug']) t3lib_div::devLog($message, $this->extKey, 3, $report);
					throw new Exception($message, 1299257894);
				}
				// cache the data for 30 seconds or as defined in TS 
				$cacheId = md5($data);
				$cacheTags = array($this->prefixId, $urlTools->createValidTagFromUrl($uri['uri']));
				try {
					$this->localCache->set(
						$cacheId,		// string - cache identifier 
						$data,			// mixed - data to cache
						$cacheTags,		// array - tags to add to the cache entry
						$this->extConf['cacheTime'] ? $this->extConf['cacheTime'] : 30			// int - lifetime in seconds
					);
				} catch(\InvalidArgumentException $e) {
					if ($this->extConf['debug'] || TYPO3_DLOG) t3lib_div::devLog('invalid tag (\'/^[a-zA-Z0-9_%\-&]{1,250}$/\') [see data] or identifier:' . $cacheId . ' e->msg: ' . $e->getMessage(), $this->extKey, 2, $cacheTags);				
				}
			
				if($this->extConf->clearCacheOnImport) tslib_fe::clearPageCacheContent_pidList($this->extConf->clearCacheOnImport);
			}
				// Check if the current charset is the same as the file encoding
				// Don't do the check if no encoding was defined
				// TODO: add automatic encoding detection by the reading the encoding attribute in the XML header
			if (empty($parameters['encoding'])) {
				$isSameCharset = TRUE;
			} else {
					// Standardize charset name and compare
				$encoding = $this->lang->csConvObj->parse_charset($parameters['encoding']);
				$isSameCharset = $this->lang->charSet == $encoding;
			}
				// If the charset is not the same, convert data
				// NOTE: example values for testing conversion:
				//		uri = http://www.rususa.com/tools/rss/social.asp-rss-newsrus
				//		encoding = windows-1251
			if (!$isSameCharset) {
				$data = $this->lang->csConvObj->conv($data, $encoding, $this->lang->charSet);
			}
				//Sometimes an API does not offer an XML result option... attempt to convert it.
			if($notation == 'xml' && $uri['notation'] == 'convertJsonToXml') {
				$data = tx_svconnector_utility::arrayToXml(json_decode($data));
			}
/*			if( ($parameters['network'] == 'flickr') && ($notation == 'jsonp') ) {
				//trim jsonFlickrApi()
			} */
		}

			// Return the result
		return $data;
	}
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/svconnector_social/sv1/class.tx_svconnectorsocial_sv1.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/svconnector_social/sv1/class.tx_svconnectorsocial_sv1.php']);
}
?>
