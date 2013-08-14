<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.
class PleskMultiServer_Loader
{
    public static function init($params)
    {
    	spl_autoload_register(array('PleskMultiServer_Loader', 'autoload'));

        $port = $params['serveraccesshash'] ? $params['serveraccesshash'] :
            ($params['serversecure'] ? 8443 : 8880);

        // save action name in registry. It is important for a correct debug.
        list(, $caller) = debug_backtrace(false);
        PleskMultiServer_Registry::getInstance()->actionName = $caller['function'];
        PleskMultiServer_Registry::getInstance()->translator = new PleskMultiServer_Translate();
        PleskMultiServer_Registry::getInstance()->api = new PleskMultiServer_Api($params['serverusername'], $params['serverpassword'], $params['serverhostname'], $port, $params['serversecure']);
    	
    	$manager = new PleskMultiServer_Manager_V1000();
    	foreach ($manager->getSupportedApiVersions() as $version) {
            $managerClassName = 'PleskMultiServer_Manager_V' . str_replace('.', '', $version);
            if (class_exists($managerClassName)) {
                PleskMultiServer_Registry::getInstance()->manager = new $managerClassName();
                break;
            }
        }
        
        if (!isset(PleskMultiServer_Registry::getInstance()->manager)) {
            throw new Exception(PleskMultiServer_Registry::getInstance()->translator->translate('ERROR_NO_APPROPRIATE_MANAGER'));
        }
    }
    
    public static function autoload($className) {
    	$filePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
    	if (file_exists($filePath)) {
            require_once $filePath;
    	}
    }
}
