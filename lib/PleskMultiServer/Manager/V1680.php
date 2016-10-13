<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.
class PleskMultiServer_Manager_V1680 extends PleskMultiServer_Manager_V1660
{
    const SETTING_NAME_PREFIX = 'ext-plesk-multi-server:';

    /**
     * @param array $params
     *
     * @return array
     */
    protected function _getIps($params)
    {
        return array();
    }

    protected function _addWebspace($params)
    {
        $this->_checkRestrictions($params);

        $requestParams = [
            'domain' => $params['domain'],
            'ownerId' => $params['ownerId'],
            'username' => $params['username'],
            'password' => $params['password'],
            'status' => PleskMultiServer_Object_Webspace::STATUS_ACTIVE,
            'htype' => PleskMultiServer_Object_Webspace::TYPE_VRT_HST,
            'planName' => $params['configoption1'],
            'requestSettings' => $this->_getFilter($params),
        ];
        PleskMultiServer_Registry::getInstance()->api->webspace_add($requestParams);
    }

    /**
     * @param array $params
     * @return array
     */
    protected function _getFilter(array $params)
    {
        $params['addAddonDedicatedIPv4'] = false;
        $params['addAddonDedicatedIPv6'] = false;

        if (!empty($params['configoptions'])) {
            foreach($params['configoptions'] as $addonTitle => $value) {
                if ("0" == $value) {
                    continue;
                }
                if (PleskMultiServer_Object_Ip::ADDON_NAME_IPV6 == $addonTitle) {
                    $params['addAddonDedicatedIPv6'] = true;
                    continue;
                }
                if (PleskMultiServer_Object_Ip::ADDON_NAME_IPV4 == $addonTitle) {
                    $params['addAddonDedicatedIPv4'] = true;
                    continue;
                }
            }
        }

        $filter = array();
        switch(PleskMultiServer_Object_Ip::getIpOption($params)) {
            case 'IPv4 shared; IPv6 none':
                $filter[$this->_getIpv4SettingName()] = $this->_getIpv4Filter($params);
                break;
            case 'IPv4 none; IPv6 shared':
                $filter[$this->_getIpv6SettingName()] = $this->_getIpv6Filter($params);
                break;
            case 'IPv4 shared; IPv6 shared':
                $filter[$this->_getIpv4SettingName()] = $this->_getIpv4Filter($params);
                $filter[$this->_getIpv6SettingName()] = $this->_getIpv6Filter($params);
                break;
            case 'IPv4 dedicated; IPv6 none':
                $filter[$this->_getIpv4SettingName()] = PleskMultiServer_Object_Ip::DEDICATED;
                break;
            case 'IPv4 none; IPv6 dedicated':
                $filter[$this->_getIpv6SettingName()] = PleskMultiServer_Object_Ip::DEDICATED;
                break;
            case 'IPv4 shared; IPv6 dedicated':
                $filter[$this->_getIpv4SettingName()] = $this->_getIpv4Filter($params);
                $filter[$this->_getIpv6SettingName()] = PleskMultiServer_Object_Ip::DEDICATED;
                break;
            case 'IPv4 dedicated; IPv6 shared':
                $filter[$this->_getIpv4SettingName()] = PleskMultiServer_Object_Ip::DEDICATED;
                $filter[$this->_getIpv6SettingName()] = $this->_getIpv6Filter($params);
                break;
            case 'IPv4 dedicated; IPv6 dedicated':
                $filter[$this->_getIpv4SettingName()] = PleskMultiServer_Object_Ip::DEDICATED;
                $filter[$this->_getIpv6SettingName()] = PleskMultiServer_Object_Ip::DEDICATED;
                break;
        }
        return $filter;
    }

    /**
     * @param array $params
     * @return string
     */
    protected function _getIpv4Filter(array $params)
    {
        return $params['addAddonDedicatedIPv4']
            ? PleskMultiServer_Object_Ip::DEDICATED
            : PleskMultiServer_Object_Ip::SHARED;
    }

    /**
     * @param array $params
     * @return string
     */
    protected function _getIpv6Filter(array $params)
    {
        return $params['addAddonDedicatedIPv6']
            ? PleskMultiServer_Object_Ip::DEDICATED
            : PleskMultiServer_Object_Ip::SHARED;
    }

    protected function _getIpv4SettingName()
    {
        return static::SETTING_NAME_PREFIX . PleskMultiServer_Object_Ip::IPV4;
    }
    protected function _getIpv6SettingName()
    {
        return static::SETTING_NAME_PREFIX . PleskMultiServer_Object_Ip::IPV6;
    }

    protected function _changeSubscriptionIp($params)
    {
        /* Commented until appropriate changes in Plesk Hub will be implemented
        $result = PleskMultiServer_Registry::getInstance()->api->webspace_subscriptions_get_by_name(array('domain' => $params['domain']));
        $webspaceId = (int)$result->webspace->get->result->id;
        PleskMultiServer_Registry::getInstance()->api->webspace_set_ip([
            'domain' => $params['domain'],
            'ipv4Address' => isset($params['ipv4Address']) ? $params['ipv4Address'] : '',
            'ipv6Address' => isset($params['ipv6Address']) ? $params['ipv6Address'] : '',
        ]);*/
    }

    /* Methods without any changes - just to use 1.6.8.0 API protocol where request-settings node exists in xsd-schemes */

    protected function _createSession($params)
    {
        return parent::_createSession($params);
    }

    protected function _processAddons($params)
    {
        parent::_processAddons($params);
    }

    protected function _setResellerStatus($params)
    {
        parent::_setResellerStatus($params);
    }

    protected function _deleteReseller($params)
    {
        parent::_deleteReseller($params);
    }

    protected function _setAccountPassword($params)
    {
        parent::_setAccountPassword($params);
    }

    protected function _deleteWebspace($params)
    {
        parent::_deleteWebspace($params);
    }

    protected function _switchSubscription($params)
    {
        parent::_switchSubscription($params);
    }

    protected function _getWebspacesUsage($params)
    {
        $params['requestSettings'] = [];
        return parent::_getWebspacesUsage($params);
    }

    protected function _setWebspaceStatus($params)
    {
        $params['requestSettings'] = array();
        parent::_setWebspaceStatus($params);
    }

    protected function _getAccountInfo($params, $panelExternalId = null)
    {
        return parent::_getAccountInfo($params, $panelExternalId = null);
    }

    protected function _setWebspacePassword($params)
    {
        $params['requestSettings'] = array();
        parent::_setWebspacePassword($params);
    }

    protected function _getClientAreaForm($params)
    {
        return parent::_getClientAreaForm($params);
    }
}
