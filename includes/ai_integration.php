<?php
declare(strict_types=1);

require_once __DIR__ . '/../ai/ai_helper.php';

function ai_helper(): AIHelper
{
    static $helper = null;
    if ($helper instanceof AIHelper) {
        return $helper;
    }
    $helper = new AIHelper();
    return $helper;
}
