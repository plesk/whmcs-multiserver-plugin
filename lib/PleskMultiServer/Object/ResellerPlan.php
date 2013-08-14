<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.
class PleskMultiServer_Object_ResellerPlan
{
	public $id;
    public $name;
    
    public function __construct($id, $name)
    {
        $this->id = $id;
        $this->name = $name;
    }

    public static function getResellerPlanName($params)
    {
        //TODO vbaranovskiy: change configoption1 to correct option name when resellers will be supported by Plesk Multi Server
        return $params['configoption1'];
    }
}
