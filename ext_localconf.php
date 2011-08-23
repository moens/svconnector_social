<?php
if (!defined ('TYPO3_MODE')) {
 	die ('Access denied.');
}

t3lib_cache::enableCachingFramework();
// If cache is not already defined, define it
if (!is_array($TYPO3_CONF_VARS['SYS']['caching']['cacheConfigurations']['svconnector_social'])) {
	 $TYPO3_CONF_VARS['SYS']['caching']['cacheConfigurations']['svconnector_social'] = array(
			'backend' => 't3lib_cache_backend_DbBackend',
			'options' => array(
				'cacheTable' => 'tx_svconnectorsocial_cache',
				'tagsTable' => 'tx_svconnectorsocial_cache_tags',
			)
	);
}
t3lib_extMgm::addService($_EXTKEY,  'connector' /* sv type */,  'tx_svconnectorsocial_sv1' /* sv key */,
		array(

			'title' => 'Social RSS Feed connector',
			'description' => 'Connector service to social networks',

			'subtype' => 'social',

			'available' => TRUE,
			'priority' => 50,
			'quality' => 50,

			'os' => '',
			'exec' => '',

			'classFile' => t3lib_extMgm::extPath($_EXTKEY).'Classes/Controller/sv1/class.tx_svconnectorsocial_sv1.php',
			'className' => 'tx_svconnectorsocial_sv1',
		)
	);
?>
