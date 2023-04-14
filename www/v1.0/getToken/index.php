<?php
include dirname(__file__) . "/../../lib/TradeApi.php";

// 거래소 api는 토큰을 전달 받을때만 작동하도록 되어 있어서 로그인시 token을 생성해 줍니다.
$tradeapi->token = session_create_id();
session_start();

// validate parameters

// --------------------------------------------------------------------------- //


// response
$tradeapi->success(array('token'=>session_id()));
