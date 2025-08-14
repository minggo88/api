<?php
header('Content-Type: application/json');
echo json_encode([
    'post' => $_POST,
    'files' => $_FILES,
    'method' => $_SERVER['REQUEST_METHOD']
]);
?>