<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.
require_once 'lib/PleskMultiServer/Loader.php';

use Illuminate\Database\Capsule\Manager as Capsule;

function pleskMultiServer_MetaData() {
    return array(
        'DisplayName' => 'Plesk Multi Server',
        'APIVersion' => '1.1',
    );
}

/**
 * @return array
 */
function pleskMultiServer_ConfigOptions($params)
{
    require_once 'lib/PleskMultiServer/Translate.php';
    $translator = new PleskMultiServer_Translate();

    $configarray = array(
        "servicePlanName" => array(
            "FriendlyName" => $translator->translate("CONFIG_SERVICE_PLAN_NAME"),
            "Type" => "text",
            "Size" => "25"
        ),
        "ipAdresses" => array (
            "FriendlyName" => $translator->translate("CONFIG_WHICH_IP_ADDRESSES"),
            "Type" => "dropdown",
            "Options" => "IPv4 shared; IPv6 none,IPv4 dedicated; IPv6 none,IPv4 none; IPv6 shared,IPv4 none; IPv6 dedicated,IPv4 shared; IPv6 shared,IPv4 shared; IPv6 dedicated,IPv4 dedicated; IPv6 shared,IPv4 dedicated; IPv6 dedicated",
            "Default" => "IPv4 shared; IPv6 none",
            "Description" => "",
        ),
    );

    return $configarray;
}

/**
 * @param $params
 * @return string
 */
function pleskMultiServer_AdminLink($params)
{
    $address = ($params['serverhostname']) ? $params['serverhostname'] : $params['serverip'];
    $port = ($params["serveraccesshash"]) ? $params["serveraccesshash"] : '8443';
    $secure = ($params["serversecure"]) ? 'https' : 'http';
    if (empty($address)) {
        return '';
    }

    $form = sprintf(
        '<form action="%s://%s:%s/login_up.php3" method="post" target="_blank">' .
        '<input type="hidden" name="login_name" value="%s" />' .
        '<input type="hidden" name="passwd" value="%s" />' .
        '<input type="submit" value="%s">' .
        '</form>',
        $secure,
        WHMCS\Input\Sanitize::encode($address),
        WHMCS\Input\Sanitize::encode($port),
        WHMCS\Input\Sanitize::encode($params["serverusername"]),
        WHMCS\Input\Sanitize::encode($params["serverpassword"]),
        'Login to panel'
    );

    return $form;
}

/**
 * @param $params
 * @return string
 */
function pleskMultiServer_ClientArea($params) {
    try {
        PleskMultiServer_Loader::init($params);
        return PleskMultiServer_Registry::getInstance()->manager->getClientAreaForm($params);

    } catch (Exception $e) {
        return PleskMultiServer_Registry::getInstance()->translator->translate('ERROR_COMMON_MESSAGE', array('CODE' => $e->getCode(), 'MESSAGE' => $e->getMessage()));
    }
}

/**
 * Create panel reseller or customer with webspace. If customer exists function add webspace to him.
 * @param $params
 * @return string
 */
function pleskMultiServer_CreateAccount($params) {

    try {

        PleskMultiServer_Loader::init($params);
        $translator = PleskMultiServer_Registry::getInstance()->translator;

        if ("" == $params['clientsdetails']['firstname'] && "" == $params['clientsdetails']['lastname']) {
            return $translator->translate('ERROR_ACCOUNT_VALIDATION_EMPTY_FIRST_OR_LASTNAME');
        } elseif ("" == $params["username"]) {
            return $translator->translate('ERROR_ACCOUNT_VALIDATION_EMPTY_USERNAME');
        }

        PleskMultiServer_Registry::getInstance()->manager->createTableForAccountStorage();

        /** @var stdClass $account */
        $account = Capsule::table('mod_pleskmsaccounts')
            ->where('userid', $params['clientsdetails']['userid'])
            ->where('usertype', $params['type'])
            ->first();

        $panelExternalId = is_null($account) ? '' : $account->panelexternalid;
        $params['clientsdetails']['panelExternalId'] = $panelExternalId;

        $accountId = null;
        try{
            $accountInfo = PleskMultiServer_Registry::getInstance()->manager->getAccountInfo($params, $panelExternalId);
            if (isset($accountInfo['id'])) {
                $accountId = $accountInfo['id'];
            }
        } catch (Exception $e) {
            if (PleskMultiServer_Api::ERROR_OBJECT_NOT_FOUND != $e->getCode()) {
                throw $e;
            }
        }

        if (!is_null($accountId) && PleskMultiServer_Object_Customer::TYPE_RESELLER == $params['type']) {
            return $translator->translate('ERROR_RESELLER_ACCOUNT_IS_ALREADY_EXISTS', array('EMAIL' => $params['clientsdetails']['email']));
        }

        $params = array_merge($params, PleskMultiServer_Registry::getInstance()->manager->getIps($params));
        if (is_null($accountId)) {
            try {
                $accountId = PleskMultiServer_Registry::getInstance()->manager->addAccount($params);
            } catch (Exception $e) {
                if (PleskMultiServer_Api::ERROR_OPERATION_FAILED == $e->getCode()) {
                    return $translator->translate('ERROR_ACCOUNT_CREATE_COMMON_MESSAGE');
                }
                throw $e;
            }
        }
        PleskMultiServer_Registry::getInstance()->manager->addIpToIpPool($accountId, $params);

        if ('' == $panelExternalId && '' != ($possibleExternalId = PleskMultiServer_Registry::getInstance()->manager->getCustomerExternalId($params))) {
            /** @var stdClass $account */
            Capsule::table('mod_pleskmsaccounts')
                ->insert(
                    array(
                        'userid' => $params['clientsdetails']['userid'],
                        'usertype' => $params['type'],
                        'panelexternalid' => $possibleExternalId
                    )
                );
        }

        if (!is_null($accountId) && PleskMultiServer_Object_Customer::TYPE_RESELLER == $params['type']) {
            return 'success';
        }

        $params['ownerId'] = $accountId;
        PleskMultiServer_Registry::getInstance()->manager->addWebspace($params);

        if (!empty($params['configoptions'])) {
            PleskMultiServer_Registry::getInstance()->manager->processAddons($params);
        }

        return 'success';
    } catch (Exception $e) {
        return PleskMultiServer_Registry::getInstance()->translator->translate('ERROR_COMMON_MESSAGE', array('CODE' => $e->getCode(), 'MESSAGE' => $e->getMessage()));
    }
}

/**
 * Suspend reseller account or customer's subscription (webspace)
 * @param $params
 * @return string
 */
function pleskMultiServer_SuspendAccount($params) {

    try {
        PleskMultiServer_Loader::init($params);
        $params['status'] = ('root' != $params['serverusername'] && 'admin' != $params['serverusername']) ? PleskMultiServer_Object_Customer::STATUS_SUSPENDED_BY_RESELLER : PleskMultiServer_Object_Customer::STATUS_SUSPENDED_BY_ADMIN ;

        switch ($params['type']) {
            case PleskMultiServer_Object_Customer::TYPE_CLIENT:
                PleskMultiServer_Registry::getInstance()->manager->setWebspaceStatus($params);
                break;
            case PleskMultiServer_Object_Customer::TYPE_RESELLER:
                PleskMultiServer_Registry::getInstance()->manager->setResellerStatus($params);
                break;
        }
        return 'success';

    } catch (Exception $e) {
        return PleskMultiServer_Registry::getInstance()->translator->translate('ERROR_COMMON_MESSAGE', array('CODE' => $e->getCode(), 'MESSAGE' => $e->getMessage()));
    }

}

/**
 * Unsuspend reseller account or customer's subscription (webspace)
 * @param $params
 * @return string
 */
function pleskMultiServer_UnsuspendAccount($params) {

    try {
        PleskMultiServer_Loader::init($params);
        switch ($params['type']) {
            case PleskMultiServer_Object_Customer::TYPE_CLIENT:
                $params["status"] = PleskMultiServer_Object_Webspace::STATUS_ACTIVE;
                PleskMultiServer_Registry::getInstance()->manager->setWebspaceStatus($params);
                break;
            case PleskMultiServer_Object_Customer::TYPE_RESELLER:
                $params["status"] = PleskMultiServer_Object_Customer::STATUS_ACTIVE;
                PleskMultiServer_Registry::getInstance()->manager->setResellerStatus($params);
                break;
        }
        return 'success';

    } catch (Exception $e) {
        return PleskMultiServer_Registry::getInstance()->translator->translate('ERROR_COMMON_MESSAGE', array('CODE' => $e->getCode(), 'MESSAGE' => $e->getMessage()));
    }

}

/**
 * Delete webspace or reseller from Panel
 * @param $params
 * @return string
 */
function pleskMultiServer_TerminateAccount($params) {

    try {
        PleskMultiServer_Loader::init($params);
        switch ($params['type']) {
            case PleskMultiServer_Object_Customer::TYPE_CLIENT:
                PleskMultiServer_Registry::getInstance()->manager->deleteWebspace($params);
                break;
            case PleskMultiServer_Object_Customer::TYPE_RESELLER:
                PleskMultiServer_Registry::getInstance()->manager->deleteReseller($params);
                break;
        }
        return 'success';

    } catch (Exception $e) {
        return PleskMultiServer_Registry::getInstance()->translator->translate('ERROR_COMMON_MESSAGE', array('CODE' => $e->getCode(), 'MESSAGE' => $e->getMessage()));
    }
}

/**
 * @param $params
 * @return string
 */
function pleskMultiServer_ChangePassword($params) {

    try {
        PleskMultiServer_Loader::init($params);
        PleskMultiServer_Registry::getInstance()->manager->setAccountPassword($params);
        if (PleskMultiServer_Object_Customer::TYPE_RESELLER == $params['type']) {
            return 'success';
        }

        PleskMultiServer_Registry::getInstance()->manager->setWebspacePassword($params);
        return 'success';
    } catch (Exception $e) {
        return PleskMultiServer_Registry::getInstance()->translator->translate('ERROR_COMMON_MESSAGE', array('CODE' => $e->getCode(), 'MESSAGE' => $e->getMessage()));
    }
}

function pleskMultiServer_AdminServicesTabFields($params) {

    try {
        PleskMultiServer_Loader::init($params);
        $translator = PleskMultiServer_Registry::getInstance()->translator;
        $accountInfo = PleskMultiServer_Registry::getInstance()->manager->getAccountInfo($params);
        if (!isset($accountInfo['login'])) {
            return array();
        }

        if ($accountInfo['login'] == $params["username"]) {
            return array('' => $translator->translate('FIELD_CHANGE_PASSWORD_MAIN_PACKAGE_DESCR'));
        }

        /** @var stdClass $hosting */
        $hosting = Capsule::table('tblhosting')
            ->where('username', $accountInfo['login'])
            ->where('userid', $params['clientsdetails']['userid'])
            ->first();

        $domain = is_null($hosting) ? '' : $hosting->domain;
        return array('' => $translator->translate('FIELD_CHANGE_PASSWORD_ADDITIONAL_PACKAGE_DESCR', array('PACKAGE' => $domain)));

    } catch (Exception $e) {
        return PleskMultiServer_Registry::getInstance()->translator->translate('ERROR_COMMON_MESSAGE', array('CODE' => $e->getCode(), 'MESSAGE' => $e->getMessage()));
    }
}

/**
 * @param $params
 * @return string
 */
function pleskMultiServer_ChangePackage($params) {
    try {
        PleskMultiServer_Loader::init($params);
        $params = array_merge($params, PleskMultiServer_Registry::getInstance()->manager->getIps($params));

        PleskMultiServer_Registry::getInstance()->manager->switchSubscription($params);
        if (PleskMultiServer_Object_Customer::TYPE_RESELLER == $params['type']) {
            return 'success';
        }
        PleskMultiServer_Registry::getInstance()->manager->processAddons($params);
        PleskMultiServer_Registry::getInstance()->manager->changeSubscriptionIp($params);

        return 'success';

    } catch (Exception $e) {
        return PleskMultiServer_Registry::getInstance()->translator->translate('ERROR_COMMON_MESSAGE', array('CODE' => $e->getCode(), 'MESSAGE' => $e->getMessage()));
    }
}

/**
 * @param $params
 * @return string
 */
function pleskMultiServer_UsageUpdate($params) {

    $query = Capsule::table('tblhosting')
        ->where('server', $params["serverid"]);

    $domains = array();
    /** @var stdClass $hosting */
    foreach ($query->get() as $hosting) {
        $domains[] = $hosting->domain;
    }
    $params["domains"] = $domains;

    try {
        PleskMultiServer_Loader::init($params);
        $domainsUsage = PleskMultiServer_Registry::getInstance()->manager->getWebspacesUsage($params);
    } catch (Exception $e) {
        return PleskMultiServer_Registry::getInstance()->translator->translate('ERROR_COMMON_MESSAGE', array('CODE' => $e->getCode(), 'MESSAGE' => $e->getMessage()));
    }

    foreach ( $domainsUsage as $domainName => $usage ) {
        Capsule::table('tblhosting')
            ->where('server', $params["serverid"])
            ->where('domain', $domainName)
            ->update(
                array(
                    "diskusage" => $usage['diskusage'],
                    "disklimit" => $usage['disklimit'],
                    "bwusage" => $usage['bwusage'],
                    "bwlimit" => $usage['bwlimit'],
                    "lastupdate" => Capsule::table('tblhosting')->raw('now()'),
                )
            );
    }

    return 'success';
}

function pleskMultiServer_TestConnection($params) {
    try {
        PleskMultiServer_Loader::init($params);
        $translator = PleskMultiServer_Registry::getInstance()->translator;
        return array(
            'success' => true,
        );
    } catch (Exception $e) {
        return array(
            'error' => PleskMultiServer_Registry::getInstance()->translator->translate('ERROR_COMMON_MESSAGE', array('CODE' => $e->getCode(), 'MESSAGE' => $e->getMessage())),
        );
    }
}
