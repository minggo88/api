<?php
include dirname(__file__) . "/../../lib/ExchangeApi.php";
// $exchangeapi->set_logging(true);
// $exchangeapi->set_log_dir(__dir__.'/');
// $exchangeapi->set_log_name('');
// $exchangeapi->write_log("REQUEST: " . json_encode($_REQUEST));


// validate parameters
$ip = setDefault(loadParam('ip'), $_SERVER['REMOTE_ADDR']);

// --------------------------------------------------------------------------- //

$calling_code = $exchangeapi->get_country_calling_code($ip);
$country_code = $exchangeapi->get_country_code($ip);


// response
$exchangeapi->success(array('calling_code'=>$calling_code, 'country_code'=>$country_code, 'ip'=>$ip));

