<?php
// =====================================================
// api/www/v1.0/auth/checkjoin/index.php
// 회원 가입 여부 확인
// =====================================================

include dirname(__file__) . "/../../lib/TradeApi.php";

/**
 * 가입 확인 (GOS 버전)
 */

// validate parameters
$media = checkMedia(strtolower(checkEmpty(loadParam('media'), 'media'))); // email, phone
$values = setDefault(loadParam('ids'), ''); // 이메일 또는 전화번호

$tradeapi->set_db_link('slave');

$values = explode(',', $values);
$origin_values = $values;

if($media == 'phone') {
    for($i = 0; $i < count($values); $i++) {
        $values[$i] = preg_replace('/[^0-9]/', '', $values[$i]);
        $values[$i] = $tradeapi->reset_phone_number($values[$i]);
    }
}

$r = array();

// 기존 회원 확인
$joined = check_gos_join($media, $values);
foreach($joined as $row) {
    if($row->values && !$r[$row->values]) {
        $i = array_search($row->values, $values);
        $r[$origin_values[$i]] = $row->status;
    }
}

$t = array();
foreach($r as $values => $status) {
    $t[] = array('id' => $values, 'status' => __($status));
}

$tradeapi->success($t);

function check_gos_join($media, $values) {
    global $tradeapi;
    
    if(empty($values)) return array();
    
    $field = ($media == 'email') ? 'email' : 'phone';
    $placeholders = str_repeat('?,', count($values) - 1) . '?';
    
    $sql = "SELECT {$field} as values, 
                   CASE 
                       WHEN status = 'active' THEN 'joined'
                       WHEN status = 'pending' THEN 'pending'
                       WHEN status = 'inactive' THEN 'inactive'
                       ELSE 'unknown'
                   END as status
            FROM GOS_users 
            WHERE {$field} IN ({$placeholders}) 
            AND deleted_at IS NULL";
    
    return $tradeapi->query($sql, $values);
}

?>