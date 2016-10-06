<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.
use Illuminate\Database\Capsule\Manager as Capsule;

class PleskMultiServer_Utils
{
    /** Gets Plesk's accounts count for user by id
     * @param int $userId
     * @return int
     */
    public static function getAccountsCount($userId)
    {
        return Capsule::table('tblhosting')
            ->join('tblservers', 'tblservers.id', '=', 'tblhosting.server')
            ->where('tblhosting.userid', $userId)
            ->where('tblservers.type', 'pleskMultiServer')
            ->whereIn('tblhosting.domainstatus', array('Active', 'Suspended', 'Pending'))
            ->count();
    }

}
