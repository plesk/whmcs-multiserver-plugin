<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.
class PleskMultiServer_Manager_V1660 extends PleskMultiServer_Manager_V1640
{
    /**
     * @param array $params
     * @return array
     */
    protected function _getAddAccountParams($params)
    {
        $result = parent::_getAddAccountParams($params);
        $result['powerUser'] = (isset($params['configoption4']) && 'on' === $params['configoption4']) ? 'true' : 'false';
        return $result;
    }

    protected function _addAccount($params)
    {
        return parent::_addAccount($params);
    }
}
