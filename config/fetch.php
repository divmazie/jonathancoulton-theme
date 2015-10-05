<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 8/19/15
 * Time: 16:44
 */

use FetchApp\API\FetchApp;

$fetch = new FetchApp();

$fetch_key = get_field('fetch_key','options');
$fetch_token = get_field('fetch_token','options');
// Set the Authentication data (needed for all requests)
$fetch->setAuthenticationKey($fetch_key);
$fetch->setAuthenticationToken($fetch_token);

return $fetch;