<?php
require_once __DIR__ . '/ai_helper.php';

$ai = new AIHelper();

$response = $ai->chat("Сгенеруй 2 тест кейса для авторизації користувачів у веб-додатку.");
print_r($response);