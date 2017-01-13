<?php
$cfg['blowfish_secret'] = getenv('BLOWFISH_SECRET');
$cfg['LoginCookieRecall'] = false;

/*
 * Servers configuration
 */
$i = 0;

/*
 * First server
 */
$i++;
/* Authentication type */
$cfg['Servers'][$i]['auth_type'] = 'cookie';
/* Server parameters */
$cfg['Servers'][$i]['host'] = getenv('PMA_HOST');
$cfg['Servers'][$i]['port'] = getenv('PMA_PORT');
$cfg['Servers'][$i]['connect_type'] = getenv('PMA_PROTO');
$cfg['Servers'][$i]['compress'] = false;
$cfg['Servers'][$i]['AllowNoPassword'] = false;
$cfg['Servers'][$i]['AllowRoot'] = true;
$cfg['Servers'][$i]['hide_db'] = '(performance_schema|information_schema|phpmyadmin|mysql)';


?>