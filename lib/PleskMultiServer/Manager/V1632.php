<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.
class PleskMultiServer_Manager_V1632 extends PleskMultiServer_Manager_V1630
{
    protected function _processAddons($params)
    {
        parent::_processAddons($params);
    }

    protected function _addWebspace($params)
    {
        parent::_addWebspace($params);
    }

    protected function _getSharedIpv4($params)
    {
        return $this->_getIp($params);
    }

    protected function _getSharedIpv6($params)
    {
        return $this->_getIp($params, PleskMultiServer_Object_Ip::IPV6);
    }

    protected function _getFreeDedicatedIpv4()
    {
        return $this->_getFreeDedicatedIp();
    }

    protected function _getFreeDedicatedIpv6()
    {
        return $this->_getFreeDedicatedIp(PleskMultiServer_Object_Ip::IPV6);
    }

    protected function _getIpList($type = PleskMultiServer_Object_Ip::SHARED, $version = null)
    {
        $ipList = array();
        static $result = null;
        if (is_null($result)) {
            $result = PleskMultiServer_Registry::getInstance()->api->ip_get();
        }
        foreach ($result->ip->get->result->addresses->ip_info as $item) {
            if ($type !== (string)$item->type) {
                continue;
            }
            $ip = (string)$item->ip_address;
            if (PleskMultiServer_Object_Ip::IPV6 == $version && !$this->_isIpv6($ip)) {
                continue;
            }
            if (PleskMultiServer_Object_Ip::IPV4 == $version && $this->_isIpv6($ip)) {
                continue;
            }
            $ipList[] = $ip;
        }

        return $ipList;
    }

    protected function _getFreeDedicatedIp($version = PleskMultiServer_Object_Ip::IPV4)
    {
        static $domains = null;
        $ipListUse = array();
        $ipListFree = array();
        $ipList = $this->_getIpList(PleskMultiServer_Object_Ip::DEDICATED, $version);
        if (is_null($domains)) {
            $domains = PleskMultiServer_Registry::getInstance()->api->webspaces_get();
        }
        foreach($domains->xpath('//webspace/get/result') as $item) {
            try {
                $this->_checkErrors($item);
                foreach($item->data->hosting->vrt_hst->ip_address as $ip) {
                    $ipListUse[(string)$ip] = (string)$ip;
                }
            } catch (Exception $e) {
                if (PleskMultiServer_Api::ERROR_OBJECT_NOT_FOUND != $e->getCode()) {
                    throw $e;
                }
            }
        }

        foreach($ipList as $ip) {
            if (!in_array($ip, $ipListUse)) {
                $ipListFree[] = $ip;
            }
        }

        $freeIp = reset($ipListFree);
        if (empty($freeIp)) {
            throw new Exception(PleskMultiServer_Registry::getInstance()->translator->translate('ERROR_NO_FREE_DEDICATED_IPTYPE', array('TYPE' => PleskMultiServer_Object_Ip::IPV6 == $version ? 'IPv6' : 'IPv4')));
        }

        return $freeIp;
    }

    /**
     * @param $params
     * @return array (<domainName> => array ('diskusage' => value, 'disklimit' => value, 'bwusage' => value, 'bwlimit' => value))
     */
    protected function _getWebspacesUsage($params)
    {
        return parent::_getWebspacesUsage($params);
    }

    protected function _changeSubscriptionIp($params)
    {
        $webspace = PleskMultiServer_Registry::getInstance()->api->webspace_get_by_name(array('domain' => $params['domain']));
        $ipDedicatedList = $this->_getIpList(PleskMultiServer_Object_Ip::DEDICATED);
        foreach($webspace->webspace->get->result->data->hosting->vrt_hst->ip_address as $ip) {
            $ip = (string)$ip;
            $oldIp[$this->_isIpv6($ip) ? PleskMultiServer_Object_Ip::IPV6 : PleskMultiServer_Object_Ip::IPV4] = $ip;
        }
        $ipv4Address = isset($oldIp[PleskMultiServer_Object_Ip::IPV4]) ? $oldIp[PleskMultiServer_Object_Ip::IPV4] : '';
        $ipv6Address = isset($oldIp[PleskMultiServer_Object_Ip::IPV6]) ? $oldIp[PleskMultiServer_Object_Ip::IPV6] : '';

        if (
            PleskMultiServer_Object_Ip::getIpOption($params) == 'IPv4 none; IPv6 shared'
            || PleskMultiServer_Object_Ip::getIpOption($params) == 'IPv4 none; IPv6 dedicated'
        ) {
            $ipv4Address = '';
        }
        if (
            PleskMultiServer_Object_Ip::getIpOption($params) == 'IPv4 shared; IPv6 none'
            || PleskMultiServer_Object_Ip::getIpOption($params) == 'IPv4 dedicated; IPv6 none'
        ) {
            $ipv6Address = '';
        }

        if (!empty($params['ipv4Address'])) {
            if (isset($oldIp[PleskMultiServer_Object_Ip::IPV4]) && ($oldIp[PleskMultiServer_Object_Ip::IPV4] != $params['ipv4Address']) &&
                (!in_array($oldIp[PleskMultiServer_Object_Ip::IPV4], $ipDedicatedList) || !in_array($params['ipv4Address'], $ipDedicatedList))) {
                $ipv4Address = $params['ipv4Address'];
            } elseif (!isset($oldIp[PleskMultiServer_Object_Ip::IPV4])) {
                $ipv4Address = $params['ipv4Address'];
            }
        }

        if (!empty($params['ipv6Address'])) {
            if (isset($oldIp[PleskMultiServer_Object_Ip::IPV6]) && ($oldIp[PleskMultiServer_Object_Ip::IPV6] != $params['ipv6Address']) &&
                (!in_array($oldIp[PleskMultiServer_Object_Ip::IPV6], $ipDedicatedList) || !in_array($params['ipv6Address'], $ipDedicatedList))) {
                $ipv6Address = $params['ipv6Address'];
            } elseif (!isset($oldIp[PleskMultiServer_Object_Ip::IPV6])) {
                $ipv6Address = $params['ipv6Address'];
            }
        }

        if (!empty($ipv4Address) || !empty($ipv6Address)) {
            PleskMultiServer_Registry::getInstance()->api->webspace_set_ip(
                array(
                    'domain' => $params['domain'],
                    'ipv4Address' => $ipv4Address,
                    'ipv6Address' => $ipv6Address,
                )
            );
        }
    }
}
