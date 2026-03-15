<?php

/**
 * 码支付通知测试工具
 * CodePay Notification Test Tool
 * 
 * 此文件用于测试向商户发送支付通知，或作为商户接收通知的示例
 */

require_once 'vendor/autoload.php';

use AliMPay\Core\CodePay;
use AliMPay\Utils\Logger;

// 检查是否是测试模式
$isTestMode = isset($_GET['test']) || isset($_POST['test']);

if ($isTestMode) {
    // 测试模式：手动触发通知
    testNotification();
} else {
    // 正常模式：作为商户接收通知的示例
    handleNotification();
}

/**
 * 测试通知功能
 */
function testNotification()
{
    try {
        $codePay = new CodePay();
        $logger = Logger::getInstance();
        
        // 获取测试参数
        $outTradeNo = $_GET['out_trade_no'] ?? $_POST['out_trade_no'] ?? '';
        
        if (empty($outTradeNo)) {
            echo json_encode([
                'code' => -1,
                'msg' => '缺少订单号参数 out_trade_no'
            ]);
            return;
        }
        
        // 查询订单
        $merchantInfo = $codePay->getMerchantInfo();
        $order = $codePay->queryOrder($merchantInfo['id'], $merchantInfo['key'], $outTradeNo);
        
        if ($order['code'] !== 1) {
            echo json_encode([
                'code' => -1,
                'msg' => '订单不存在: ' . $order['msg']
            ]);
            return;
        }
        
        // 模拟支付成功，发送通知
        $result = $codePay->sendNotification([
            'id' => $order['trade_no'],
            'out_trade_no' => $order['out_trade_no'],
            'pid' => $order['pid'],
            'type' => $order['type'],
            'name' => $order['name'],
            'price' => $order['money'],
            'notify_url' => 'https://example.com/notify_handler.php'  // 这里应该是实际的商户通知URL
        ]);
        
        if ($result) {
            echo json_encode([
                'code' => 1,
                'msg' => '通知发送成功'
            ]);
        } else {
            echo json_encode([
                'code' => -1,
                'msg' => '通知发送失败'
            ]);
        }
        
    } catch (Exception $e) {
        Logger::getInstance()->error('Test notification error', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        
        echo json_encode([
            'code' => -1,
            'msg' => $e->getMessage()
        ]);
    }
}

/**
 * 处理商户通知示例
 * 这是商户应该如何处理来自码支付系统的通知
 */
function handleNotification()
{
    try {
        $logger = Logger::getInstance();
        
        // 获取通知参数
        $params = [
            'pid' => $_GET['pid'] ?? $_POST['pid'] ?? '',
            'trade_no' => $_GET['trade_no'] ?? $_POST['trade_no'] ?? '',
            'out_trade_no' => $_GET['out_trade_no'] ?? $_POST['out_trade_no'] ?? '',
            'type' => $_GET['type'] ?? $_POST['type'] ?? '',
            'name' => $_GET['name'] ?? $_POST['name'] ?? '',
            'money' => $_GET['money'] ?? $_POST['money'] ?? '',
            'trade_status' => $_GET['trade_status'] ?? $_POST['trade_status'] ?? '',
            'sign' => $_GET['sign'] ?? $_POST['sign'] ?? '',
            'sign_type' => $_GET['sign_type'] ?? $_POST['sign_type'] ?? 'MD5'
        ];
        
        $logger->info('Received payment notification', [
            'params' => array_merge($params, ['sign' => '***']),
            'method' => $_SERVER['REQUEST_METHOD'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        // 验证必要参数
        $requiredParams = ['pid', 'trade_no', 'out_trade_no', 'type', 'name', 'money', 'trade_status', 'sign'];
        foreach ($requiredParams as $param) {
            if (empty($params[$param])) {
                $logger->error("Missing required parameter: {$param}");
                echo 'fail';
                return;
            }
        }
        
        // 验证签名
        if (!verifyNotificationSignature($params)) {
            $logger->error('Invalid signature in notification');
            echo 'fail';
            return;
        }
        
        // 验证交易状态
        if ($params['trade_status'] !== 'TRADE_SUCCESS') {
            $logger->error('Invalid trade status: ' . $params['trade_status']);
            echo 'fail';
            return;
        }
        
        // 处理支付成功逻辑
        $success = processPaymentSuccess($params);
        
        if ($success) {
            $logger->info('Payment notification processed successfully', [
                'out_trade_no' => $params['out_trade_no'],
                'trade_no' => $params['trade_no'],
                'money' => $params['money']
            ]);
            echo 'success';
        } else {
            $logger->error('Failed to process payment notification');
            echo 'fail';
        }
        
    } catch (Exception $e) {
        Logger::getInstance()->error('Notification processing error', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        
        echo 'fail';
    }
}

/**
 * 验证通知签名
 */
function verifyNotificationSignature($params)
{
    // 这里应该使用您的商户密钥
    $merchantKey = 'your_merchant_key_here';
    
    $sign = $params['sign'];
    unset($params['sign'], $params['sign_type']);
    
    // 按照码支付协议生成签名
    $params = array_filter($params, function($value) {
        return $value !== '' && $value !== null;
    });
    
    ksort($params);
    $parts = [];
    foreach ($params as $key => $value) {
        $parts[] = $key . '=' . $value;
    }
    
    $signStr = implode('&', $parts);
    $expectedSign = md5($signStr . $merchantKey);
    
    return $sign === $expectedSign;
}

/**
 * 处理支付成功逻辑
 */
function processPaymentSuccess($params)
{
    // 这里应该实现您的业务逻辑
    // 例如：更新订单状态、发送邮件通知、记录日志等
    
    $outTradeNo = $params['out_trade_no'];
    $tradeNo = $params['trade_no'];
    $amount = $params['money'];
    $productName = $params['name'];
    
    // 示例：更新订单状态
    // updateOrderStatus($outTradeNo, 'paid');
    
    // 示例：发送邮件通知
    // sendEmailNotification($outTradeNo, $amount);
    
    // 示例：记录到数据库
    // recordPaymentLog($outTradeNo, $tradeNo, $amount);
    
    return true;
}

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>码支付通知测试</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .section {
            background: #f5f5f5;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .form-group {
            margin: 10px 0;
        }
        label {
            display: inline-block;
            width: 150px;
            font-weight: bold;
        }
        input[type="text"] {
            width: 300px;
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
        button {
            background: #007cba;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        button:hover {
            background: #005a87;
        }
        .code {
            background: #f8f8f8;
            padding: 10px;
            border-left: 4px solid #007cba;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <h1>码支付通知测试工具</h1>
    
    <div class="section">
        <h2>1. 测试通知发送</h2>
        <p>输入订单号，测试向商户发送支付通知：</p>
        <form method="GET" action="notify.php">
            <input type="hidden" name="test" value="1">
            <div class="form-group">
                <label>订单号：</label>
                <input type="text" name="out_trade_no" placeholder="输入商户订单号" required>
            </div>
            <button type="submit">发送测试通知</button>
        </form>
    </div>
    
    <div class="section">
        <h2>2. 商户接收通知示例</h2>
        <p>商户应该在其服务器上创建一个类似的接收通知的页面。</p>
        
        <h3>URL示例：</h3>
        <div class="code">
            GET https://your-domain.com/notify_handler.php?pid=1001&trade_no=123&out_trade_no=ORDER123&type=alipay&name=商品&money=0.01&trade_status=TRADE_SUCCESS&sign=abc123&sign_type=MD5
        </div>
        
        <h3>PHP处理示例：</h3>
        <div class="code">
            <pre><?php echo htmlspecialchars('<?php
// 验证签名
if (verifySignature($_GET, $merchantKey)) {
    if ($_GET["trade_status"] === "TRADE_SUCCESS") {
        // 更新订单状态为已支付
        updateOrderStatus($_GET["out_trade_no"], "paid");
        echo "success";
    } else {
        echo "fail";
    }
} else {
    echo "fail";
}
?>'); ?></pre>
        </div>
    </div>
    
    <div class="section">
        <h2>3. 重要说明</h2>
        <ul>
            <li><strong>自动检查：</strong> 系统会自动检查支付宝账单，发现支付后主动通知商户</li>
            <li><strong>通知地址：</strong> 商户在创建订单时提供的 notify_url</li>
            <li><strong>签名验证：</strong> 商户必须验证通知的签名以确保安全性</li>
            <li><strong>响应要求：</strong> 商户必须返回 "success" 表示接收成功，否则系统会重试</li>
            <li><strong>幂等性：</strong> 商户应该处理重复通知，确保订单状态正确</li>
        </ul>
    </div>
</body>
</html> 