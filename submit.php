<?php

require_once 'vendor/autoload.php';

use AliMPay\Core\CodePay;
use AliMPay\Utils\Logger;

$logger = Logger::getInstance();

try {
    // 兼容POST和GET请求
    $requestData = !empty($_POST) ? $_POST : $_GET;

    $params = [
        'pid' => $requestData['pid'] ?? '',
        'type' => $requestData['type'] ?? '',
        'out_trade_no' => $requestData['out_trade_no'] ?? '',
        'notify_url' => $requestData['notify_url'] ?? '',
        'return_url' => $requestData['return_url'] ?? '',
        'name' => $requestData['name'] ?? '',
        'money' => $requestData['money'] ?? '',
        'sitename' => $requestData['sitename'] ?? '',
        'sign' => $requestData['sign'] ?? '',
        'sign_type' => $requestData['sign_type'] ?? 'MD5'
    ];
    
    // 如果是直接从api.php重定向而来，参数可能在POST中
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($requestData['payment_result'])) {
        $result = json_decode($requestData['payment_result'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('无效的支付结果数据');
        }
    } else {
        // 对于直接访问或GET请求，需要重新创建支付
        $logger->info('CodePay Payment Submit Request', [
            'params' => array_merge($params, ['sign' => '***']), // Hide signature in logs
            'method' => $_SERVER['REQUEST_METHOD'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        $codePay = new CodePay();
        $result = $codePay->createPayment($params);
    }
    
    if ($result['code'] !== 1) {
        throw new Exception($result['msg']);
    }
    
    // 优先使用实际支付金额
    $displayAmount = $result['payment_amount'] ?? $params['money'];
    $logger->info('Payment page generated successfully.', ['out_trade_no' => $params['out_trade_no']]);
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>码支付 - 支付宝支付</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f5f5f5;
                margin: 0;
                padding: 20px;
            }
            .container {
                max-width: 500px;
                margin: 0 auto;
                background: white;
                border-radius: 10px;
                padding: 30px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
            }
            .header h1 {
                color: #1677ff;
                margin: 0;
            }
            .order-info {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
                margin-bottom: 20px;
            }
            .order-info h3 {
                margin-top: 0;
                color: #333;
            }
            .info-item {
                display: flex;
                justify-content: space-between;
                margin-bottom: 10px;
            }
            .info-label {
                color: #666;
            }
            .info-value {
                font-weight: bold;
                color: #333;
            }
            .amount {
                font-size: 24px;
                color: #ff4d4f;
            }
            .qr-code {
                text-align: center;
                margin: 20px 0;
            }
            .qr-code .qr-img-wrapper {
                display: inline-block;
                width: 220px;
                height: 220px;
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.04);
                overflow: hidden;
                position: relative;
            }
            .qr-code img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                object-position: center;
                border: 1px solid #ddd;
                background: white;
                display: block;
            }
            .payment-tips {
                background: #e6f7ff;
                border: 1px solid #91d5ff;
                border-radius: 8px;
                padding: 15px;
                margin-top: 20px;
            }
            .payment-tips h4 {
                margin-top: 0;
                color: #1677ff;
            }
            .payment-tips ul {
                margin: 10px 0;
                padding-left: 20px;
            }
            .payment-tips li {
                margin: 5px 0;
                color: #666;
            }
            .alert-warning {
                background-color: #fffbe6;
                border-color: #ffe58f;
                color: #d46b08;
                padding: 10px 15px;
                border-radius: 5px;
                margin-bottom: 20px;
            }
            .buttons {
                text-align: center;
                margin-top: 30px;
            }
            .btn {
                background: #1677ff;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                cursor: pointer;
                margin: 0 10px;
                text-decoration: none;
                display: inline-block;
            }
            .btn:hover {
                background: #0958d9;
            }
            .btn-secondary {
                background: #f5f5f5;
                color: #666;
            }
            .btn-secondary:hover {
                background: #e6e6e6;
            }
            .status {
                text-align: center;
                margin-top: 20px;
                padding: 10px;
                border-radius: 5px;
                font-weight: bold;
            }
            .status.pending {
                background: #fff7e6;
                color: #d46b08;
                border: 1px solid #ffd591;
            }
            .status.success {
                background: #f6ffed;
                color: #52c41a;
                border: 1px solid #b7eb8f;
            }
            .status.expired {
                background: #f5f5f5;
                color: #8c8c8c;
                border: 1px solid #d9d9d9;
            }
            .countdown {
                text-align: center;
                margin: 10px 0;
                padding: 8px;
                background: #fff1f0;
                color: #cf1322;
                border: 1px solid #ffa39e;
                border-radius: 5px;
                font-weight: bold;
            }
            .countdown.expired {
                background: #f5f5f5;
                color: #8c8c8c;
                border: 1px solid #d9d9d9;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>码支付</h1>
                <p>安全、快速的支付体验</p>
            </div>

            <?php if (isset($result['amount_adjusted']) && $result['amount_adjusted']): ?>
            <div class="alert-warning">
                <strong>注意：</strong> <?php echo htmlspecialchars($result['adjustment_note']); ?>
            </div>
            <?php endif; ?>
            
            <div class="order-info">
                <h3>订单信息</h3>
                <div class="info-item">
                    <span class="info-label">商品名称：</span>
                    <span class="info-value"><?php echo htmlspecialchars($params['name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">订单号：</span>
                    <span class="info-value"><?php echo htmlspecialchars($params['out_trade_no']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">内部交易号：</span>
                    <span class="info-value"><?php echo htmlspecialchars($result['trade_no']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">支付金额：</span>
                    <span class="info-value amount">¥<?php echo htmlspecialchars(number_format($displayAmount, 2)); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">支付方式：</span>
                    <span class="info-value">支付宝</span>
                </div>
                <?php if (!empty($params['sitename'])): ?>
                <div class="info-item">
                    <span class="info-label">商户名称：</span>
                    <span class="info-value"><?php echo htmlspecialchars($params['sitename']); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="status pending" id="paymentStatus">
                等待支付...
            </div>
            
            <div class="countdown" id="countdownDisplay">
                剩余支付时间：<span id="countdown">05:00</span>
            </div>
            
            <?php if (isset($result['business_qr_mode']) && $result['business_qr_mode']): ?>
                <div class="qr-code">
                    <p>请使用支付宝扫描下方二维码完成支付</p>
                    <div class="qr-img-wrapper">
                        <img src="<?php echo htmlspecialchars($result['qr_code_url']); ?>" alt="经营码收款">
                    </div>
                </div>
                <div class="payment-tips">
                    <h4>支付提示</h4>
                    <ul>
                        <?php foreach ($result['payment_tips'] as $tip): ?>
                            <li><?php echo htmlspecialchars($tip); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <div class="qr-code">
                    <p>请使用支付宝扫描下方二维码完成支付</p>
                    <div class="qr-img-wrapper">
                        <img src="data:image/png;base64,<?php echo $result['qr_code']; ?>" alt="支付宝支付">
                    </div>
                </div>
                <div class="payment-tips">
                    <h4>支付提示</h4>
                    <ul>
                        <li>请在5分钟内完成支付，超时订单将自动作废。</li>
                        <li>支付时无需填写备注，系统会自动确认。</li>
                        <li>支付完成后，页面将自动跳转。</li>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="buttons">
                <a href="<?php echo htmlspecialchars($result['payment_url']); ?>" class="btn" id="openInAlipay">打开支付宝App支付</a>
                <button class="btn btn-secondary" onclick="checkOrderStatus()">我已支付，查询订单状态</button>
            </div>
        </div>

        <script>
            let countdownInterval;
            
            function startCountdown(duration) {
                let timer = duration, minutes, seconds;
                const display = document.getElementById('countdown');
                const countdownDisplay = document.getElementById('countdownDisplay');

                countdownInterval = setInterval(function () {
                    minutes = parseInt(timer / 60, 10);
                    seconds = parseInt(timer % 60, 10);

                    minutes = minutes < 10 ? "0" + minutes : minutes;
                    seconds = seconds < 10 ? "0" + seconds : seconds;

                    display.textContent = minutes + ":" + seconds;

                    if (--timer < 0) {
                        clearInterval(countdownInterval);
                        document.getElementById('paymentStatus').textContent = '订单已超时';
                        document.getElementById('paymentStatus').className = 'status expired';
                        countdownDisplay.textContent = '订单已过期，请重新下单';
                        countdownDisplay.className = 'countdown expired';
                    }
                }, 1000);
            }

            function checkOrderStatus() {
                const statusElement = document.getElementById('paymentStatus');
                const outTradeNo = '<?php echo htmlspecialchars($result['out_trade_no']); ?>';
                const pid = '<?php echo htmlspecialchars($result['pid']); ?>';

                statusElement.textContent = '正在查询订单状态...';

                const queryUrl = '/api.php?action=order&out_trade_no=' + encodeURIComponent(outTradeNo) +
                    '&pid=' + encodeURIComponent(pid) +
                    '&_t=' + Date.now();

                fetch(queryUrl, { cache: 'no-store' })
                    .then(response => response.json())
                    .then(data => {
                        if (data.code === 1 && data.status === 1) {
                            statusElement.textContent = '支付成功';
                            statusElement.className = 'status success';
                            clearInterval(countdownInterval); // Stop countdown
                            
                            // Redirect to return_url if available
                            const returnUrl = '<?php echo htmlspecialchars($params['return_url']); ?>';
                            if (returnUrl) {
                                window.location.href = returnUrl;
                            }
                        } else if (data.code === 1 && data.status === 0) {
                            statusElement.textContent = '等待支付...';
                            statusElement.className = 'status pending';
                        } else {
                            statusElement.textContent = '查询失败，请稍后重试';
                        }
                    })
                    .catch(error => {
                        console.error('Error checking order status:', error);
                        statusElement.textContent = '查询时发生错误';
                    });
            }

            // Start countdown
            startCountdown(300); // 5 minutes

            // Periodically check order status every 5 seconds
            setInterval(checkOrderStatus, 5000);
            
            // Initial check
            checkOrderStatus();
        </script>
    </body>
    </html>
    <?php
} catch (Exception $e) {
    $logger->error('Payment page failed to generate.', ['error' => $e->getMessage()]);
    
    // Display a user-friendly error page
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>支付错误</title>
        <style>
            body { font-family: sans-serif; text-align: center; padding: 50px; }
            .error-container { max-width: 600px; margin: 0 auto; background: #fff1f0; border: 1px solid #ffa39e; padding: 20px; border-radius: 8px; }
            h1 { color: #cf1322; }
            p { color: #595959; }
        </style>
    </head>
    <body>
        <div class="error-container">
            <h1>支付请求失败</h1>
            <p>抱歉，我们无法处理您的支付请求。请检查您的参数是否正确，或联系网站管理员。</p>
            <p><strong>错误信息：</strong> <?php echo htmlspecialchars($e->getMessage()); ?></p>
        </div>
    </body>
    </html>
    <?php
} 
