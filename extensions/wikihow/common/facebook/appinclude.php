<?php
require_once 'client/facebook.php';

$appapikey = 'd9750a5976aa776dbdc1b48dc31920ee';
$appsecret = '6219a0b85473cff9dcbe154eb3d3b64e';
$facebook = new FacebookAPI($appapikey, $appsecret);
$user = $facebook->require_login();

//[todo: change the following url to your callback url]
#$appcallbackurl = 'http://wikidiy.com/facebook-platform/';  
$appcallbackurl = 'http://www.wikihow.com/Special:Facebook';  

//catch the exception that gets thrown if the cookie has an invalid session_key in it
try {
  if (!$facebook->api_client->users_isAppAdded()) {
    $facebook->redirect($facebook->get_add_url());
  }
} catch (Exception $ex) {
  //this will clear cookies for your application and redirect them to a login prompt
  $facebook->set_user(null, null);
  $facebook->redirect($appcallbackurl);
}

