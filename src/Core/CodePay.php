<?php

namespace AliMPay\Core;

use AliMPay\Utils\Logger;
use AliMPay\Utils\PushPlusNotifier;
use AliMPay\Utils\QRCodeGenerator;
use AliMPay\Core\AlipayTransfer; // Added import for AlipayTransfer

class CodePay
{
    private $logger;
    private $config;
    private $merchantId;
    private $merchantKey;
    private $configFile;
    private $ordersFile;
    private $db;
    private $pushPlusNotifier;
    
    public function __construct()
    {
        // Set Beijing timezone
        date_default_timezone_set('Asia/Shanghai');
        
        $this->logger = Logger::getInstance();
        
        // Load configurations
        $this->config = require __DIR__ . '/../../config/alipay.php';
        $this->pushPlusNotifier = new PushPlusNotifier($this->config, $this->logger);
        $this->configFile = __DIR__ . '/../../config/codepay.json';
        $this->ordersFile = __DIR__ . '/../../data/orders.json';
        
        // Ensure data directory exists
        $dataDir = dirname($this->ordersFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        $this->initializeMerchant();
    }
    
    /**
     * Get the database instance.
     * @return \Medoo\Medoo
     */
    public function getDb()
    {
        return $this->db;
    }

    /**
     * Get the current merchant's configuration.
     * @return array
     */
    public function getMerchantInfo()
    {
        return [
            'id' => $this->merchantId,
            'key' => $this->merchantKey,
            'notify_url' => $this->config['codepay_notify_url'] ?? '', // Assuming notify_url might be in config
            'query_minutes_back' => $this->config['payment']['query_minutes_back'] ?? 30
        ];
    }

    /**
     * Initialize merchant ID and key
     */
    private function initializeMerchant(): void
    {
        if (file_exists($this->configFile)) {
            $config = json_decode(file_get_contents($this->configFile), true);
            $this->merchantId = $config['merchant_id'];
            $this->merchantKey = $config['merchant_key'];
            $this->logger->info('Loaded existing merchant configuration', ['merchant_id' => $this->merchantId]);
        } else {
            // Generate new merchant ID and key
            $this->merchantId = '1001' . str_pad(rand(0, 999999999999), 12, '0', STR_PAD_LEFT);
            $this->merchantKey = bin2hex(random_bytes(16));
            
            $config = [
                'merchant_id' => $this->merchantId,
                'merchant_key' => $this->merchantKey,
                'created_at' => date('Y-m-d H:i:s'),
                'status' => 1,
                'balance' => '0.00',
                'rate' => '96'
            ];
            
            file_put_contents($this->configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->logger->info('Generated new merchant configuration', ['merchant_id' => $this->merchantId]);
        }

        // Initialize database
        $this->initializeDatabase();
    }

    private function initializeDatabase()
    {
        $databaseFile = __DIR__ . '/../../data/codepay.db';
        $this->db = new \Medoo\Medoo([
            'database_type' => 'sqlite',
            'database_file' => $databaseFile,
            'database_name' => 'codepay'
        ]);
        $this->logger->info('Database initialized.', ['file' => $databaseFile]);

        // Create tables if they don't exist
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS codepay_orders (
                id VARCHAR(32) PRIMARY KEY,
                out_trade_no VARCHAR(64) NOT NULL,
                type VARCHAR(10) NOT NULL,
                pid VARCHAR(20) NOT NULL,
                name VARCHAR(255) NOT NULL,
                price DECIMAL(10, 2) NOT NULL,
                payment_amount DECIMAL(10, 2) DEFAULT 0,
                status TINYINT(1) DEFAULT 0,
                add_time DATETIME NOT NULL,
                pay_time DATETIME,
                notify_url VARCHAR(255),
                return_url VARCHAR(255),
                sitename VARCHAR(255)
            );
        ");
        
        // 检查表结构，确保payment_amount字段存在
        $columns = $this->db->query("PRAGMA table_info(codepay_orders);")->fetchAll();
        $hasPaymentAmount = false;
        foreach ($columns as $column) {
            if ($column['name'] === 'payment_amount') {
                $hasPaymentAmount = true;
                break;
            }
        }
        
        if (!$hasPaymentAmount) {
            $this->db->exec("ALTER TABLE codepay_orders ADD COLUMN payment_amount DECIMAL(10, 2) DEFAULT 0");
            $this->logger->info('Added payment_amount column to existing table.');
        }
        
        $this->logger->info('Database table codepay_orders initialized.', ['has_payment_amount' => $hasPaymentAmount]);
    }
    
    /**
     * Validate signature according to CodePay protocol.
     * 码支付协议签名验证
     */
    private function validateSignature(array $params, bool $isNotification = false): bool
    {
        if (!isset($params['sign'])) {
            $this->logger->warning('Signature validation failed: sign parameter missing.');
            return false;
        }

        $sign = $params['sign'];
        
        // Remove sign and sign_type from params
        unset($params['sign'], $params['sign_type']);
        
        // Generate sign string according to CodePay protocol
        $signStr = $this->generateSignString($params);
        $expectedSign = md5($signStr . $this->merchantKey);
        
        $this->logger->debug('Validating signature according to CodePay protocol.', [
            'string_to_sign' => $signStr,
            'expected_sign' => $expectedSign,
            'received_sign' => $sign
        ]);

        return $sign === $expectedSign;
    }

    /**
     * Generate sign string according to CodePay protocol
     * 按照码支付协议生成签名字符串
     */
    private function generateSignString(array $params): string
    {
        // Remove empty values
        $params = array_filter($params, function($value) {
            return $value !== '' && $value !== null;
        });
        
        // Sort parameters by key name in ascending order
        ksort($params);
        
        // Build query string
        $parts = [];
        foreach ($params as $key => $value) {
            $parts[] = $key . '=' . $value;
        }
        
        return implode('&', $parts);
    }

    /**
     * Generate signature for response according to CodePay protocol
     * 按照码支付协议生成响应签名
     */
    private function generateResponseSignature(array $params): string
    {
        $signStr = $this->generateSignString($params);
        return md5($signStr . $this->merchantKey);
    }

    /**
     * Query merchant information according to CodePay protocol
     * 按照码支付协议查询商户信息
     */
    public function queryMerchant(string $pid, string $key): array
    {
        $this->logger->info('Querying merchant info according to CodePay protocol.', ['pid' => $pid]);
        try {
            if ($pid !== $this->merchantId || $key !== $this->merchantKey) {
                return [
                    'code' => -1,
                    'msg' => 'Invalid merchant credentials'
                ];
            }

            $response = [
                'code' => 1,
                'pid' => (int)$this->merchantId,
                'key' => $this->merchantKey,
                'qq' => null,
                'active' => 1,
                'money' => '0.00',
                'account' => $this->config['transfer_user_id'] ?? 'Not Set',
                'username' => 'Merchant',
                'rate' => '96',
                'issmrz' => 1
            ];

            $this->logger->info('Merchant query successful.', ['pid' => $pid]);
            return $response;

        } catch (\Exception $e) {
            $this->logger->error('Failed to query merchant info.', ['error' => $e->getMessage(), 'pid' => $pid]);
            return [
                'code' => -1,
                'msg' => $e->getMessage()
            ];
        }
    }

    /**
     * Create payment request according to CodePay protocol
     * 按照码支付协议创建支付请求
     */
    public function createPayment(array $params): array
    {
        $this->logger->info('Creating payment according to CodePay protocol.', ['out_trade_no' => $params['out_trade_no']]);
        try {
            // Validate required parameters
            $this->validatePaymentParams($params);
            
            // Generate internal trade number
            $tradeNo = $this->generateTradeNo();
            
            // 检查是否启用经营码收款模式
            $businessQrMode = $this->config['payment']['business_qr_mode']['enabled'] ?? false;
            $originalAmount = (float)$params['money'];
            $paymentAmount = $originalAmount;
            
            $this->logger->info('Payment mode check.', [
                'business_qr_mode' => $businessQrMode,
                'original_amount' => $originalAmount,
                'out_trade_no' => $params['out_trade_no']
            ]);
            
            if ($businessQrMode) {
                // 使用原子操作来分配唯一的支付金额
                $offset = $this->config['payment']['business_qr_mode']['amount_offset'] ?? 0.01;
                $paymentAmount = $this->allocateUniqueAmount($originalAmount, $offset);
                
                if ($paymentAmount != $originalAmount) {
                    $this->logger->info('Amount adjusted to avoid conflicts.', [
                        'original_amount' => $originalAmount,
                        'adjusted_amount' => $paymentAmount,
                        'offset' => $offset,
                        'out_trade_no' => $params['out_trade_no']
                    ]);
                }
            }
            
            // Create order record in the database
            $this->db->insert('codepay_orders', [
                'id' => $tradeNo,
                'out_trade_no' => $params['out_trade_no'],
                'type' => $params['type'],
                'pid' => $params['pid'],
                'name' => $params['name'],
                'price' => $originalAmount,  // 存储原始金额
                'payment_amount' => $paymentAmount,  // 存储实际支付金额
                'status' => 0,
                'add_time' => date('Y-m-d H:i:s'),
                'notify_url' => $params['notify_url'],
                'return_url' => $params['return_url'],
                'sitename' => $params['sitename'] ?? ''
            ]);
            
            $this->logger->info('Order record created in database.', [
                'trade_no' => $tradeNo,
                'original_amount' => $originalAmount,
                'payment_amount' => $paymentAmount
            ]);

            // 根据收款模式生成不同的支付二维码
            if ($businessQrMode) {
                // 经营码收款模式：使用上传的经营码二维码
                $qrCodePath = $this->config['payment']['business_qr_mode']['qr_code_path'];
                
                if (!file_exists($qrCodePath)) {
                    throw new \Exception('经营码二维码文件不存在，请先上传经营码到: ' . $qrCodePath);
                }
                
                // 生成二维码访问URL
                $token = md5('qrcode_access_' . date('Y-m-d'));
                $baseUrl = $this->getBaseUrl();
                $qrCodeUrl = $baseUrl . '/qrcode.php?type=business&token=' . $token;
                
                $paymentUrl = '经营码收款模式';  // 经营码模式不需要支付URL
                $qrCodeBase64 = null;  // 经营码模式不使用base64
                
                $this->logger->info('Using business QR code for payment.', [
                    'trade_no' => $tradeNo,
                    'payment_amount' => $paymentAmount,
                    'qr_code_path' => $qrCodePath,
                    'qr_code_url' => $qrCodeUrl
                ]);
            } else {
                // 传统转账模式：动态生成转账二维码
                $alipayTransfer = new AlipayTransfer($this->config);
                $paymentUrl = $alipayTransfer->createOrder(
                    $params['out_trade_no'],
                    $paymentAmount,  // 使用调整后的金额
                    $params['name']
                );

                // Generate QR code
                $qrCodeGenerator = new QRCodeGenerator();
                $qrCodeBase64 = $qrCodeGenerator->generate($paymentUrl);
                $qrCodeUrl = null;  // 传统模式不使用URL
                
                $this->logger->info('Using transfer QR code for payment.', [
                    'trade_no' => $tradeNo,
                    'payment_url' => $paymentUrl
                ]);
            }

            $response = [
                'code' => 1,
                'msg' => 'SUCCESS',
                'pid' => $params['pid'], // 增加pid确保返回
                'trade_no' => $tradeNo,
                'out_trade_no' => $params['out_trade_no'],
                'money' => $params['money'],  // 原始金额
                'payment_amount' => $paymentAmount,  // 实际支付金额
                'payment_url' => $paymentUrl
            ];
            
            // 根据收款模式添加二维码字段
            if ($businessQrMode) {
                $response['qr_code_url'] = $qrCodeUrl;  // 经营码模式使用URL
            } else {
                $response['qr_code'] = $qrCodeBase64;   // 传统模式使用base64
            }
            
            // 如果启用了经营码收款模式，添加详细信息
            if ($businessQrMode) {
                $response['business_qr_mode'] = true;
                $response['payment_instruction'] = "请使用支付宝扫描二维码，支付金额：{$paymentAmount} 元";
                
                if ($paymentAmount != $originalAmount) {
                    $response['amount_adjusted'] = true;
                    $response['adjustment_note'] = "检测到相同金额订单，实际支付金额已调整为 {$paymentAmount} 元";
                    $response['original_amount'] = $originalAmount;
                }
                
                $response['payment_tips'] = [
                    "请务必支付准确金额：{$paymentAmount} 元",
                    "支付时无需填写备注信息",
                    "请在5分钟内完成支付，超时订单将被自动删除",
                    "支付完成后系统会自动检测到账",
                    "如长时间未到账，请联系客服"
                ];
            }

            $this->logger->info('Payment created successfully.', ['trade_no' => $tradeNo]);
            return $response;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to create payment.', ['error' => $e->getMessage(), 'out_trade_no' => $params['out_trade_no'] ?? 'N/A']);
            return [
                'code' => -1,
                'msg' => $e->getMessage()
            ];
        }
    }

    /**
     * Get the base URL for the current request
     * @return string
     */
    private function getBaseUrl(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $port = $_SERVER['SERVER_PORT'] ?? '80';
        
        // 如果是标准端口，不需要显示端口号
        if (($protocol === 'https' && $port === '443') || ($protocol === 'http' && $port === '80')) {
            return $protocol . '://' . $host;
        }
        
        return $protocol . '://' . $host . ':' . $port;
    }

    /**
     * Validate payment parameters and signature.
     * @param array $params
     * @throws \InvalidArgumentException
     */
    private function validatePaymentParams(array $params): void
    {
        $requiredParams = ['pid', 'type', 'out_trade_no', 'notify_url', 'return_url', 'name', 'money', 'sign'];
        foreach ($requiredParams as $param) {
            if (!isset($params[$param]) || $params[$param] === '') {
                throw new \InvalidArgumentException("Missing required parameter: {$param}");
            }
        }
        
        // Validate merchant
        if ($params['pid'] !== $this->merchantId) {
            throw new \InvalidArgumentException("Invalid merchant ID. Expected: {$this->merchantId}, Got: {$params['pid']}");
        }
        
        // Validate payment type (only support alipay)
        if ($params['type'] !== 'alipay') {
            throw new \InvalidArgumentException("Only 'alipay' payment type is supported. Got: {$params['type']}");
        }
        
        // Validate signature
        if (!$this->validateSignature($params)) {
            throw new \InvalidArgumentException('Invalid signature');
        }
        $this->logger->debug('Payment parameters validated successfully.', ['out_trade_no' => $params['out_trade_no']]);
    }

    /**
     * Generate trade number
     * 生成交易号
     */
    private function generateTradeNo(): string
    {
        return date('YmdHis') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Allocate unique payment amount by checking database for duplicates
     * 通过检查数据库重复金额来分配唯一的支付金额
     */
    private function allocateUniqueAmount(float $originalAmount, float $offset): float
    {
        $lockFile = __DIR__ . '/../../data/amount_allocation.lock';
        $lockHandle = fopen($lockFile, 'w');

        if ($lockHandle === false) {
            $this->logger->error('Failed to create lock file for amount allocation.');
            throw new \Exception('无法创建锁文件，请检查目录权限');
        }

        // Acquire an exclusive lock (blocking)
        if (!flock($lockHandle, LOCK_EX)) {
            $this->logger->error('Failed to acquire lock for amount allocation.');
            fclose($lockHandle);
            throw new \Exception('无法获取金额分配锁，请稍后重试');
        }

        try {
            // 获取配置的超时时间
            $timeoutSeconds = $this->config['payment']['order_timeout'] ?? 300;
            $startTime = date('Y-m-d H:i:s', time() - $timeoutSeconds);

            $this->logger->info('Starting unique amount allocation.', [
                'original_amount' => $originalAmount,
                'offset' => $offset,
                'timeout_seconds' => $timeoutSeconds,
                'start_time' => $startTime
            ]);

            $paymentAmount = $originalAmount;
            $attempts = 0;
            $maxAttempts = 100; // 防止无限循环

            // 循环检查直到找到不重复的金额
            while ($attempts < $maxAttempts) {
                $attempts++;

                // 检查当前金额是否已存在
                $existingOrder = $this->db->get('codepay_orders', ['id', 'out_trade_no', 'add_time'], [
                    'payment_amount' => $paymentAmount,
                    'status' => 0,
                    'add_time[>=]' => $startTime
                ]);

                if (!$existingOrder) {
                    // 如果不存在重复，则使用当前金额
                    $this->logger->info('Unique amount allocated successfully.', [
                        'original_amount' => $originalAmount,
                        'final_amount' => $paymentAmount,
                        'attempts' => $attempts,
                        'adjusted' => $paymentAmount != $originalAmount
                    ]);
                    break;
                }

                // 如果存在重复，则增加偏移量
                $this->logger->info('Payment amount conflict detected, adjusting amount.', [
                    'conflicting_amount' => $paymentAmount,
                    'existing_order_id' => $existingOrder['id'],
                    'existing_order_trade_no' => $existingOrder['out_trade_no'],
                    'existing_order_time' => $existingOrder['add_time'],
                    'attempt' => $attempts
                ]);

                $paymentAmount += $offset;
            }

            if ($attempts >= $maxAttempts) {
                $this->logger->error('Failed to allocate unique amount after maximum attempts.', [
                    'original_amount' => $originalAmount,
                    'final_amount' => $paymentAmount,
                    'max_attempts' => $maxAttempts
                ]);
                throw new \Exception('无法分配唯一的支付金额，请稍后重试');
            }

            return $paymentAmount;

        } finally {
            // Release the lock
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    /**
     * Query single order according to CodePay protocol
     * 按照码支付协议查询单个订单
     */
    public function queryOrder(string $pid, ?string $key, string $outTradeNo, bool $validateKey = true): array
    {
        $this->logger->info('Querying order according to CodePay protocol.', ['out_trade_no' => $outTradeNo, 'pid' => $pid]);
        try {
            if ($validateKey && ($pid !== $this->merchantId || $key !== $this->merchantKey)) {
                return [
                    'code' => -1,
                    'msg' => 'Invalid merchant credentials'
                ];
            }

            if (!$validateKey && $pid !== $this->merchantId) {
                return [
                    'code' => -1,
                    'msg' => 'Invalid merchant ID'
                ];
            }

            // Prefer a paid record when duplicates exist for the same out_trade_no.
            $order = $this->db->get('codepay_orders', '*', [
                'out_trade_no' => $outTradeNo,
                'pid' => $pid,
                'ORDER' => [
                    'status' => 'DESC',
                    'add_time' => 'DESC',
                    'id' => 'DESC'
                ]
            ]);

            if (!$order) {
                return [
                    'code' => -1,
                    'msg' => 'Order not found'
                ];
            }

            $response = [
                'code' => 1,
                'msg' => 'SUCCESS',
                'trade_no' => $order['id'],
                'out_trade_no' => $order['out_trade_no'],
                'type' => $order['type'],
                'pid' => $order['pid'],
                'addtime' => $order['add_time'],
                'endtime' => $order['pay_time'],
                'name' => $order['name'],
                'money' => number_format($order['price'], 2, '.', ''),
                'status' => (int)$order['status']
            ];

            $this->logger->info('Order query successful.', ['out_trade_no' => $outTradeNo, 'status' => $order['status']]);
            return $response;

        } catch (\Exception $e) {
            $this->logger->error('Failed to query order.', ['error' => $e->getMessage(), 'out_trade_no' => $outTradeNo]);
            return [
                'code' => -1,
                'msg' => $e->getMessage()
            ];
        }
    }

    /**
     * Query multiple orders according to CodePay protocol
     * 按照码支付协议查询多个订单
     */
    public function queryOrders(string $pid, string $key, int $limit = 20): array
    {
        $this->logger->info('Querying orders according to CodePay protocol.', ['pid' => $pid, 'limit' => $limit]);
        try {
            if ($pid !== $this->merchantId || $key !== $this->merchantKey) {
                return [
                    'code' => -1,
                    'msg' => 'Invalid merchant credentials'
                ];
            }

            $orders = $this->db->select('codepay_orders', '*', [
                'pid' => $pid,
                'ORDER' => ['add_time' => 'DESC'],
                'LIMIT' => max(1, min($limit, 100))
            ]);

            $result = [];
            foreach ($orders as $order) {
                $result[] = [
                    'trade_no' => $order['id'],
                    'out_trade_no' => $order['out_trade_no'],
                    'type' => $order['type'],
                    'pid' => $order['pid'],
                    'addtime' => $order['add_time'],
                    'endtime' => $order['pay_time'],
                    'name' => $order['name'],
                    'money' => number_format($order['price'], 2, '.', ''),
                    'status' => (int)$order['status']
                ];
            }

            $this->logger->info('Orders query successful.', ['count' => count($result)]);
            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Failed to query orders.', ['error' => $e->getMessage(), 'pid' => $pid]);
            return [
                'code' => -1,
                'msg' => $e->getMessage()
            ];
        }
    }

    /**
     * Process payment notification according to CodePay protocol
     * 按照码支付协议处理支付通知
     */
    public function processNotification(array $params): array
    {
        $this->logger->info('Processing payment notification according to CodePay protocol.', ['out_trade_no' => $params['out_trade_no'] ?? 'N/A']);
        try {
            // Validate required parameters
            $requiredParams = ['out_trade_no', 'trade_no', 'trade_status', 'name', 'money'];
            foreach ($requiredParams as $param) {
                if (!isset($params[$param])) {
                    throw new \InvalidArgumentException("Missing required parameter: {$param}");
                }
            }

            // Validate signature
            if (!$this->validateSignature($params, true)) {
                throw new \InvalidArgumentException('Invalid signature');
            }

            // Find target order; if duplicates exist, prioritize latest pending record.
            $order = $this->db->get('codepay_orders', '*', [
                'out_trade_no' => $params['out_trade_no'],
                'ORDER' => [
                    'status' => 'ASC',
                    'add_time' => 'DESC',
                    'id' => 'DESC'
                ]
            ]);

            if (!$order) {
                throw new \Exception("Order not found: {$params['out_trade_no']}");
            }

            if ($order['status'] == 1) {
                $this->logger->info('Order already paid, ignoring notification.', ['out_trade_no' => $params['out_trade_no']]);
                return [
                    'code' => 1,
                    'msg' => 'SUCCESS'
                ];
            }

            // Update order status
            if ($params['trade_status'] === 'TRADE_SUCCESS') {
                $this->db->update('codepay_orders', [
                    'status' => 1,
                    'pay_time' => date('Y-m-d H:i:s')
                ], ['id' => $order['id']]);
                
                $this->logger->info('Order status updated to paid.', ['out_trade_no' => $params['out_trade_no']]);
                $order['pay_time'] = date('Y-m-d H:i:s');
                $this->sendPushPlusNotification($order, 'codepay_notify_api');
            }

            return [
                'code' => 1,
                'msg' => 'SUCCESS'
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to process notification.', ['error' => $e->getMessage(), 'out_trade_no' => $params['out_trade_no'] ?? 'N/A']);
            return [
                'code' => -1,
                'msg' => $e->getMessage()
            ];
        }
    }

    /**
     * Send notification to merchant according to CodePay protocol
     * 按照码支付协议向商户发送通知
     */
    public function sendNotification(array $orderData): bool
    {
        if (empty($orderData['notify_url'])) {
            $this->logger->warning('No notify_url provided for order.', ['out_trade_no' => $orderData['out_trade_no']]);
            return false;
        }

        try {
            $notifyData = [
                'pid' => $orderData['pid'],
                'trade_no' => $orderData['id'],
                'out_trade_no' => $orderData['out_trade_no'],
                'type' => $orderData['type'],
                'name' => $orderData['name'],
                'money' => number_format($orderData['price'], 2, '.', ''),
                'trade_status' => 'TRADE_SUCCESS'
            ];

            // Generate signature
            $notifyData['sign'] = $this->generateResponseSignature($notifyData);
            $notifyData['sign_type'] = 'MD5';

            // Send notification
            $url = $orderData['notify_url'];
            $queryString = http_build_query($notifyData);
            $fullUrl = $url . (strpos($url, '?') !== false ? '&' : '?') . $queryString;

            $this->logger->info('Sending notification to merchant.', [
                'out_trade_no' => $orderData['out_trade_no'],
                'notify_url' => $orderData['notify_url']
            ]);

            $response = file_get_contents($fullUrl);
            $success = ($response === 'success' || $response === 'SUCCESS');

            if ($success) {
                $this->logger->info('Notification sent successfully.', ['out_trade_no' => $orderData['out_trade_no']]);
            } else {
                $this->logger->warning('Notification failed or invalid response.', [
                    'out_trade_no' => $orderData['out_trade_no'],
                    'response' => $response
                ]);
            }

            return $success;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send notification.', [
                'error' => $e->getMessage(),
                'out_trade_no' => $orderData['out_trade_no']
            ]);
            return false;
        }
    }

    public function sendPushPlusNotification(array $orderData, string $detectedBy = 'payment_monitor'): bool
    {
        return $this->pushPlusNotifier->sendPaymentSuccess($orderData, $detectedBy);
    }

    public function sendSuccessNotifications(array $orderData, string $detectedBy = 'payment_monitor'): array
    {
        $merchantSent = false;
        if (!empty($orderData['notify_url'])) {
            $merchantSent = $this->sendNotification($orderData);
        } else {
            $this->logger->warning('No notify_url provided for order.', ['out_trade_no' => $orderData['out_trade_no'] ?? '']);
        }

        $pushPlusSent = $this->sendPushPlusNotification($orderData, $detectedBy);

        return [
            'merchant' => $merchantSent,
            'pushplus' => $pushPlusSent
        ];
    }
}
