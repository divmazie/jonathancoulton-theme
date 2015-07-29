<?php
/**
 * Created by PhpStorm.
 * User: DAM
 * Date: 7/28/15
 * Time: 16:34
 */
header('Content-type: application/json');
$content = array("woo" => "hoo", "v" => $params['var']);
echo json_encode($content);
?>
