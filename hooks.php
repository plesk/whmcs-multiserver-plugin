<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.
/**
 * Plesk Multi Server Module Hooks
 */
use Illuminate\Database\Capsule\Manager as Capsule;


add_hook('ShoppingCartValidateCheckout', 1, function ($vars)
{
    require_once 'lib/PleskMultiServer/Translate.php';
    require_once 'lib/PleskMultiServer/Config.php';
    require_once 'lib/PleskMultiServer/Utils.php';
    $translator = new PleskMultiServer_Translate();
    $accountLimit = (int)PleskMultiServer_Config::get()->account_limit;
    if (0 >= $accountLimit) {
        return array();
    }

    $accountCount = ('new' == $vars['custtype']) ? 0 : PleskMultiServer_Utils::getAccountsCount($vars['userid']);
    $pleskAccountsInCart = 0;
    foreach($_SESSION['cart']['products'] as $product) {
        $currentProduct = Capsule::table('tblproducts')->where('id', $product['pid'])->first();
        if ('pleskMultiServer' == $currentProduct->servertype) {
            $pleskAccountsInCart++;
        }
    }
    if (!$pleskAccountsInCart) {
        return array();
    }
    $summaryAccounts = $accountCount + $pleskAccountsInCart;

    $errors = array();
    if (0 < $accountLimit && $summaryAccounts > $accountLimit) {
        $errors[] = $translator->translate(
            'ERROR_RESTRICTIONS_ACCOUNT_COUNT',
            array('ACCOUNT_LIMIT' => $accountLimit)
        );
    }

    return $errors;
});
