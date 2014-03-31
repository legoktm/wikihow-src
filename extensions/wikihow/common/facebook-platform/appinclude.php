<?php

wfDebug("before app require\n");
require_once 'php/facebook.php';

wfDebug("before setting api key\n");
# HOW TO KEYS
$appapikey = 'd9750a5976aa776dbdc1b48dc31920ee';
$appsecret = '6219a0b85473cff9dcbe154eb3d3b64e';
# MONKEY KEYS
#$appapikey = 'b5292932e1af3a3aac7e8eead9c2f4eb';
#$appsecret = '953cbb6dc2c35c234f6cc3cfc23f53ee';

$facebook = new Facebook($appapikey, $appsecret);
$user = $facebook->require_login();

//[todo: change the following url to your callback url]
$appcallbackurl = 'http://www.wikihow.com/Special:FacebookPage';  
wfDebug("Got here\n");

//catch the exception that gets thrown if the cookie has an invalid session_key in it
try {
  if (!$facebook->api_client->users_isAppAdded()) {
wfDebug("isAppAdded\n");
    $facebook->redirect($facebook->get_add_url());
  }
} catch (Exception $ex) {
  //this will clear cookies for your application and redirect them to a login prompt
  $facebook->set_user(null, null);
  $facebook->redirect($appcallbackurl);
}

