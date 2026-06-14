<?php

namespace TypechoPlugin\Icefox;

use Typecho\Widget;
use Widget\ActionInterface;

/**
 * Memo API 路由处理器
 * 
 * 处理 /v1/memo 路由请求，提供朋友圈结构化JSON数据
 */
class MemoApi extends Widget implements ActionInterface
{
    public function action()
    {
        // 加载主题函数库以使用 handleMemoApi()
        $functionsFile = __TYPECHO_ROOT_DIR__ . '/usr/themes/icefox/functions.php';
        if (is_file($functionsFile)) {
            require_once $functionsFile;
        } else {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['code' => -1, 'message' => 'Theme functions not found']);
            exit;
        }

        if (function_exists('handleMemoApi')) {
            handleMemoApi();
        } else {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['code' => -1, 'message' => 'handleMemoApi function not found']);
            exit;
        }
    }
}
