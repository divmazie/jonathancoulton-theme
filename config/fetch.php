<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 8/19/15
 * Time: 16:44
 */

use FetchApp\API\FetchApp;

$fetch = new FetchApp();

// Set the Authentication data (needed for all requests)
$fetch->setAuthenticationKey("joco");
$fetch->setAuthenticationToken("7e35d7e0fb27");

return $fetch;