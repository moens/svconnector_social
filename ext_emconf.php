<?php

########################################################################
# Extension Manager/Repository config file for ext "svconnector_social".
#
# Auto generated 04-05-2011 09:35
#
# Manual updates:
# Only the data in the array - everything else is removed by next
# writing. "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Connector service - SOCIAL',
	'description' => 'Connector service for Social Newtorks that require some sort of authentication, and from which a dynamic feed address is likely',
	'category' => 'services',
	'shy' => 0,
	'version' => '0.5.0',
	'dependencies' => '',
	'conflicts' => '',
	'priority' => '',
	'loadOrder' => '',
	'module' => '',
	'state' => 'stable',
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => '',
	'clearcacheonload' => 0,
	'lockType' => '',
	'author' => 'Sy Moen, Francois Suter',
	'author_email' => 'tech@gallupcurrent.com, typo3@cobweb.ch',
	'author_company' => 'Gallup Current, Cobweb',
	'CGLcompliance' => '',
	'CGLcompliance_note' => '',
	'constraints' => array(
		'depends' => array(
			'typo3' => '4.3.0-0.0.0',
			'svconnector' => '2.0.0-0.0.0',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
);

?>
