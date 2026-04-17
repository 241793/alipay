<?php

namespace AliMPay\Core;

use AliMPay\Core\BillQuery;
use AliMPay\Utils\Logger;
use Medoo\Medoo;

class PaymentMonitor
{
    private $billQuery;
    private $logger;
    private $db;
    private $codepay_config;
    
    public function __construct(BillQuery $billQuery, Medoo $db, array $codepay_config)
    {
        // Set Beijing timezone
        date_default_timezone_set('Asia/Shanghai');
        
        $this->billQuery = $billQuery;
        $this->db = $db;
        $this->codepay_config = $codepay_config;
        $this->logger = Logger::getInstance();
    }
    
    private function loadConfig(): array
    {
        $configPath = __DIR__ . '/../../config/alipay.php';
        return file_exists($configPath) ? require $configPath : [];
    }
    
    /**
     * Monitor payment status (centered on order time)
     * 
     * @param string $orderNo Order number (memo)
     * @param float $expectedAmount Expected amount
     * @param string|null $orderTime 下单时间（格式 Y-m-d H:i:s，默认当前时间）
     * @param int $hoursRange 查询范围（前后各多少小时）
     * @return array Payment result
     */
    public function monitorPayment(string $orderNo, float $expectedAmount, string $orderTime = null, int $hoursRange = 12): array
    {
        $startTime = time();
        $this->logger->info('Starting payment monitoring', [
            'order_no' => $orderNo,
            'expected_amount' => $expectedAmount,
            'max_wait_time' => $this->maxWaitTime,
            'order_time' => $orderTime,
            'hours_range' => $hoursRange
        ]);
        
        echo "开始监控支付状态...\n";
        echo "订单号: {$orderNo}\n";
        echo "期望金额: {$expectedAmount}\n";
        echo "最大等待时间: {$this->maxWaitTime}秒\n";
        echo "查询范围: 下单时间前后{$hoursRange}小时\n\n";
        
        // 计算查询时间区间
        $orderTimestamp = $orderTime ? strtotime($orderTime) : time();
        $queryStart = date('Y-m-d H:i:s', $orderTimestamp - $hoursRange * 3600);
        $queryEnd   = date('Y-m-d H:i:s', $orderTimestamp + $hoursRange * 3600);
        
        while (true) {
            $currentTime = time();
            $elapsed = $currentTime - $startTime;
            
            if ($elapsed >= $this->maxWaitTime) {
                $this->logger->warning('Payment monitoring timeout', [
                    'order_no' => $orderNo,
                    'elapsed_time' => $elapsed
                ]);
                
                return [
                    'success' => false,
                    'status' => 'timeout',
                    'message' => '支付监控超时',
                    'elapsed_time' => $elapsed
                ];
            }
            
            try {
                // 查询以下单时间为中心的账单
                $result = $this->billQuery->queryBills($queryStart, $queryEnd, null, 1, 100);
                
                if ($result['success']) {
                    $payment = $this->findPaymentByMemo($result['data'], $orderNo, $expectedAmount);
                    
                    if ($payment) {
                        $this->logger->info('Payment found', [
                            'order_no' => $orderNo,
                            'payment_data' => $payment,
                            'elapsed_time' => $elapsed
                        ]);
                        
                        echo "✓ 支付成功！\n";
                        echo "订单号: {$orderNo}\n";
                        echo "实际金额: {$payment['amount']}\n";
                        echo "支付时间: {$payment['trans_dt']}\n";
                        echo "支付状态: {$payment['status']}\n";
                        
                        return [
                            'success' => true,
                            'status' => 'paid',
                            'message' => '支付成功',
                            'payment_data' => $payment,
                            'elapsed_time' => $elapsed
                        ];
                    }
                }
                
                // Print progress
                $remainingTime = $this->maxWaitTime - $elapsed;
                echo "⏳ 等待支付... 剩余时间: {$remainingTime}秒 (查询区间: {$queryStart} ~ {$queryEnd})\r";
                
                sleep($this->checkInterval);
                
            } catch (\Exception $e) {
                $this->logger->error('Error during payment monitoring', [
                    'order_no' => $orderNo,
                    'error' => $e->getMessage(),
                    'elapsed_time' => $elapsed
                ]);
                
                echo "监控过程中发生错误: {$e->getMessage()}\n";
                sleep($this->checkInterval);
            }
        }
    }
    
    /**
     * Query recent bills
     * 
     * @param int $hoursBack How many hours back to query (default 24 hours)
     * @return array
     */
    private function queryRecentBills(int $hoursBack = 24): array
    {
        // Set Beijing timezone
        date_default_timezone_set('Asia/Shanghai');
        
        // Get current time and subtract 5 minutes for both start and end time
        $endTime = date('Y-m-d H:i:s', strtotime('-5 minutes'));
        $startTime = date('Y-m-d H:i:s', strtotime("-{$hoursBack} hours -5 minutes")); // Query last N hours minus 5 minutes
        
        $this->logger->info('Querying recent bills with Beijing time', [
            'start_time' => $startTime,
            'end_time' => $endTime,
            'hours_back' => $hoursBack,
            'timezone' => date_default_timezone_get()
        ]);
        
        return $this->billQuery->queryBills($startTime, $endTime, null, 1, 100);
    }
    
    /**
     * Query bills with custom time range
     * 
     * @param string $startTime
     * @param string $endTime
     * @return array
     */
    public function queryBillsInTimeRange(string $startTime, string $endTime): array
    {
        $this->logger->info('Querying bills in custom time range', [
            'start_time' => $startTime,
            'end_time' => $endTime
        ]);
        
        return $this->billQuery->queryBills($startTime, $endTime, null, 1, 100);
    }
    
    /**
     * Find payment by memo and amount
     * 
     * @param array $billData
     * @param string $orderNo
     * @param float $expectedAmount
     * @return array|null
     */
    private function findPaymentByMemo(array $billData, string $orderNo, float $expectedAmount): ?array
    {
        // 检查数据结构，支持多种格式
        $bills = [];
        if (isset($billData['detail_list']) && is_array($billData['detail_list'])) {
            $bills = $billData['detail_list'];
        } elseif (isset($billData['accountLogList']) && is_array($billData['accountLogList'])) {
            $bills = $billData['accountLogList'];
        } elseif (is_array($billData) && isset($billData[0])) {
            // 如果billData直接是数组格式
            $bills = $billData;
        } else {
            $this->logger->warning('Bill data is missing or not in expected format.', ['order_no' => $orderNo]);
            return null;
        }
        
        foreach ($bills as $bill) {
            // 支持多种字段名称格式
            $remark = $bill['trans_memo'] ?? ($bill['memo'] ?? ($bill['remark'] ?? ''));
            $amount = $bill['trans_amount'] ?? ($bill['amount'] ?? 0);
            $direction = $bill['direction'] ?? '';
            
            $logContext = [
                'target_order_no' => $orderNo,
                'expected_amount' => $expectedAmount,
                'bill_memo' => $remark,
                'bill_amount' => $amount,
                'bill_direction' => $direction
            ];

            // Check if it's an income transaction
            if (!empty($direction) && $direction !== '收入') {
                continue; // Skip non-income records
            }

            // The remark from Alipay should match the order number we are looking for.
            // Using trim() to avoid issues with leading/trailing whitespace.
            if (trim($remark) === $orderNo) {
                // Check if amount matches
                if (abs(floatval($amount) - $expectedAmount) < 0.01) {
                    $this->logger->info('Payment match found.', $logContext);
                    return [
                        'account_log_id' => $bill['account_log_id'] ?? '',
                        'alipay_order_no' => $bill['alipay_order_no'] ?? ($bill['alipayOrderNo'] ?? ''),
                        'amount' => $amount,
                        'trans_dt' => $bill['trans_dt'] ?? ($bill['transDate'] ?? ''),
                        'status' => $direction,
                        'trans_memo' => $remark,
                        'other_account' => $bill['other_account'] ?? '',
                        'type' => $bill['type'] ?? ''
                    ];
                } else {
                    $this->logger->debug('Order ID matched, but amount did not.', $logContext);
                }
            }
        }
        
        $this->logger->info('No matching payment found in the provided bill data.', ['order_no' => $orderNo]);
        return null;
    }
    
    /**
     * 手动搜索支付记录（下单时间为中心，前后N小时）
     * @param string $orderNo
     * @param float $expectedAmount
     * @param string|null $orderTime
     * @param int $hoursRange
     * @return array
     */
    public function searchPayment(string $orderNo, float $expectedAmount, string $orderTime = null, int $hoursRange = 12): array
    {
        $this->logger->info('Manually searching for payment', [
            'order_no' => $orderNo,
            'expected_amount' => $expectedAmount,
            'order_time' => $orderTime,
            'hours_range' => $hoursRange
        ]);
        
        try {
            $orderTimestamp = $orderTime ? strtotime($orderTime) : time();
            $queryStart = date('Y-m-d H:i:s', $orderTimestamp - $hoursRange * 3600);
            $queryEnd   = date('Y-m-d H:i:s', $orderTimestamp + $hoursRange * 3600);
            
            $this->logger->info('Executing bill query with time range', [
                'order_no' => $orderNo,
                'query_start' => $queryStart,
                'query_end' => $queryEnd
            ]);
            
            $result = $this->billQuery->queryBills($queryStart, $queryEnd, null, 1, 200);
            
            if ($result['success'] && !empty($result['data']['detail_list'])) {
                $this->logger->info('Bills query successful, found ' . count($result['data']['detail_list']) . ' records.', ['order_no' => $orderNo]);
                $payment = $this->findPaymentByMemo($result['data'], $orderNo, $expectedAmount);
                
                if ($payment) {
                    $this->logger->info('Payment found in manual search', [
                        'order_no' => $orderNo,
                        'payment_data' => $payment
                    ]);
                    
                    return [
                        'success' => true,
                        'status' => 'found',
                        'message' => '找到支付记录',
                        'payment_data' => $payment,
                        'search_range' => $queryStart . ' ~ ' . $queryEnd
                    ];
                } else {
                    $this->logger->info('Payment not found in manual search', [
                        'order_no' => $orderNo,
                        'expected_amount' => $expectedAmount,
                        'search_range' => $queryStart . ' ~ ' . $queryEnd,
                        'total_records' => count($result['data']['detail_list'] ?? [])
                    ]);
                    
                    return [
                        'success' => false,
                        'status' => 'not_found',
                        'message' => '未找到匹配的支付记录',
                        'search_range' => $queryStart . ' ~ ' . $queryEnd,
                        'total_records_checked' => count($result['data']['detail_list'] ?? [])
                    ];
                }
            } else {
                throw new \Exception('查询账单失败');
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Error during manual payment search', [
                'order_no' => $orderNo,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'status' => 'error',
                'message' => '搜索过程中发生错误: ' . $e->getMessage(),
                'search_range' => $queryStart . ' ~ ' . $queryEnd
            ];
        }
    }
    
    /**
     * Set monitoring parameters
     * 
     * @param int $maxWaitTime Maximum wait time in seconds
     * @param int $checkInterval Check interval in seconds
     * @param int $queryHoursBack Query hours back
     */
    public function setMonitoringParams(int $maxWaitTime, int $checkInterval, int $queryHoursBack = null): void
    {
        $this->maxWaitTime = $maxWaitTime;
        $this->checkInterval = $checkInterval;
        if ($queryHoursBack !== null) {
            $this->queryHoursBack = $queryHoursBack;
        }
    }
    
    /**
     * Set query time range
     * 
     * @param int $hoursBack How many hours back to query
     */
    public function setQueryHoursBack(int $hoursBack): void
    {
        $this->queryHoursBack = $hoursBack;
    }
    
    /**
     * Get current monitoring parameters
     * 
     * @return array
     */
    public function getMonitoringParams(): array
    {
        return [
            'max_wait_time' => $this->maxWaitTime,
            'check_interval' => $this->checkInterval,
            'query_hours_back' => $this->queryHoursBack,
        ];
    }

    /**
     * Run a single monitoring cycle to check and update pending orders.
     * This is designed to be triggered by a cron job or a web request.
     */
    public function runMonitoringCycle(): void
    {
        $minutes = $this->codepay_config['query_minutes_back'] ?? 30;
        $this->logger->info("Starting payment monitoring cycle for the last {$minutes} minutes...");

        // 自动清理过期订单
        $this->cleanupExpiredOrders();

        // 计算时间范围
        $endTime = date('Y-m-d H:i:s');
        $startTime = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));
        
        $this->logger->info("Querying bills from {$startTime} to {$endTime}");

        try {
            $result = $this->billQuery->queryBills($startTime, $endTime);

            if (!$result['success']) {
                $this->logger->error("Failed to query bills.", ['response' => $result['message'] ?? 'Alipay API returned an error.']);
                return;
            }
            
            $bills = $this->extractBillsFromResult($result['data']);

            if (empty($bills)) {
                $this->logger->info("No recent payment bills found in the last {$minutes} minutes.");
                return;
            }

            $this->logger->info("Found " . count($bills) . " bill(s) to process.");
            
            // 检查是否启用经营码收款模式
            $config = $this->loadConfig();
            $businessQrMode = $config['payment']['business_qr_mode']['enabled'] ?? false;
            
            if ($businessQrMode) {
                $this->processBillsForBusinessQrMode($bills);
            } else {
                $this->processBillsForTraditionalMode($bills);
            }

        } catch (\Exception $e) {
            $this->logger->error("Error during monitoring cycle: " . $e->getMessage());
        }

        $this->logger->info("Payment monitoring cycle finished.");
    }
    
    private function extractBillsFromResult(array $data): array
    {
        $bills = [];
        if (isset($data['detail_list']) && is_array($data['detail_list'])) {
            $bills = $data['detail_list'];
        } elseif (isset($data['accountLogList']) && is_array($data['accountLogList'])) {
            $bills = $data['accountLogList'];
        } elseif (is_array($data) && isset($data[0])) {
            $bills = $data;
        }

        if (empty($bills)) {
            return [];
        }

        // 转换数据格式以兼容现有逻辑
        $formattedBills = [];
        foreach ($bills as $bill) {
            // 只处理收入类型的账单
            $direction = $bill['direction'] ?? '';
            if (!empty($direction) && $direction !== '收入') {
                continue;
            }

            $formattedBills[] = [
                'tradeNo' => $bill['alipay_order_no'] ?? ($bill['alipayOrderNo'] ?? ($bill['tradeNo'] ?? '')),
                'amount' => $bill['trans_amount'] ?? ($bill['amount'] ?? 0),
                'remark' => $bill['trans_memo'] ?? ($bill['memo'] ?? ($bill['remark'] ?? '')),
                'transDate' => $bill['trans_dt'] ?? ($bill['transDate'] ?? ''),
                'balance' => $bill['balance'] ?? 0,
                'type' => $bill['type'] ?? ''
            ];
        }
        return $formattedBills;
    }

    private function processBillsForBusinessQrMode(array $bills): void
    {
        $this->logger->info("Business QR mode enabled. Using amount-based matching.");
        
        foreach ($bills as $bill) {
            $this->logger->info("Processing bill: Trade No={$bill['tradeNo']}, Amount={$bill['amount']}, Time={$bill['transDate']}");
            
            $billAmount = (float)$bill['amount'];
            
            // 获取相同金额的待支付订单
            $order = $this->db->get('codepay_orders', '*', [
                'payment_amount' => $billAmount,
                'status' => 0,
                'ORDER' => ['add_time' => 'ASC'] // 获取最早的那个
            ]);
            
            if (!$order) {
                $this->logger->info("No pending order found for amount {$billAmount}. Skipping.");
                continue;
            }
            
            // 验证时间容差
            $config = $this->loadConfig();
            $tolerance = $config['payment']['business_qr_mode']['match_tolerance'] ?? 300; // 默认5分钟
            $orderTime = strtotime($order['add_time']);
            $billTime = strtotime($bill['transDate']);

            if ($billTime < $orderTime || ($billTime - $orderTime) > $tolerance) {
                $this->logger->warning("Order found for amount {$billAmount}, but it is outside the time tolerance.", [
                    'order_id' => $order['id'],
                    'out_trade_no' => $order['out_trade_no'],
                    'order_time' => $order['add_time'],
                    'bill_time' => $bill['transDate'],
                    'time_diff' => $billTime - $orderTime,
                    'tolerance' => $tolerance
                ]);
                continue;
            }
            
            $this->logger->info("Payment match found for order {$order['id']}. Updating status to paid.", [
                'out_trade_no' => $order['out_trade_no']
            ]);

            // 使用事务确保原子性
            $this->db->action(function($db) use ($order) {
                $updated = $db->update('codepay_orders', [
                    'status' => 1,
                    'pay_time' => date('Y-m-d H:i:s')
                ], ['id' => $order['id']]);

                if ($updated->rowCount() > 0) {
                    $this->notifyUser($order);
                    $this->logger->info("Order {$order['id']} successfully marked as paid and notification sent.");
                    return true; // 确保事务提交
                } else {
                    $this->logger->warning("Failed to update order status, it might have been updated by another process.", [
                        'order_id' => $order['id']
                    ]);
                    return false; // 回滚事务
                }
            });
            
            // 由于一笔支付只应匹配一笔订单，处理完后可以跳出循环
            // 如果希望一笔账单能支付多个相同金额的订单（不推荐），可以移除break
            break; 
        }
    }

    private function processBillsForTraditionalMode(array $bills): void
    {
        $this->logger->info("Traditional mode enabled. Using memo-based matching.");

        foreach ($bills as $bill) {
            $this->logger->info("Processing bill: Trade No={$bill['tradeNo']}, Amount={$bill['amount']}, Remark={$bill['remark']}");
            $remark = $bill['remark'];

            if (empty($remark)) {
                $this->logger->info("Skipping bill with empty remark.", ['trade_no' => $bill['tradeNo']]);
                continue;
            }

            $out_trade_no = trim($remark);
            
            $order = $this->db->get('codepay_orders', '*', [
                'out_trade_no' => $out_trade_no,
                'status' => 0
            ]);

            if ($order) {
                if (abs((float)$order['price'] - (float)$bill['amount']) < 0.01) {
                    $this->logger->info("Payment match found for order {$order['id']}. Updating status to paid.", [
                        'out_trade_no' => $order['out_trade_no']
                    ]);
                    $this->db->update('codepay_orders', [
                        'status' => 1,
                        'pay_time' => date('Y-m-d H:i:s')
                    ], ['id' => $order['id']]);
                    $this->notifyUser($order);
                } else {
                    $this->logger->warning("Amount mismatch for order {$order['id']}.", [
                        'out_trade_no' => $order['out_trade_no'],
                        'expected_amount' => $order['price'],
                        'bill_amount' => $bill['amount']
                    ]);
                }
            }
        }
    }

    private function notifyUser($order)
    {
        // 检查是否有通知URL
        if (empty($order['notify_url'])) {
            $this->logger->log("No notify_url configured for order {$order['id']}. Skipping notification.");
            return;
        }

        // 使用CodePay类的标准方法发送通知，确保签名一致性
        $codePay = new \AliMPay\Core\CodePay();
        $success = $codePay->sendNotification($order);

        if ($success) {
            $this->logger->log("Merchant notification successful for order {$order['id']}.");
        } else {
            $this->logger->log("Merchant notification failed for order {$order['id']}.");
        }
    }

    /**
     * 清理过期订单
     * 删除超过指定时间的待支付订单
     */
    private function cleanupExpiredOrders(): void
    {
        $config = $this->loadConfig();
        $autoCleanup = $config['payment']['auto_cleanup'] ?? true;
        
        if (!$autoCleanup) {
            return;
        }
        
        $timeoutSeconds = $config['payment']['order_timeout'] ?? 300; // 默认5分钟
        $expiredTime = date('Y-m-d H:i:s', time() - $timeoutSeconds);
        
        try {
            // 查询过期的待支付订单
            $expiredOrders = $this->db->select('codepay_orders', ['id', 'out_trade_no', 'add_time'], [
                'status' => 0,  // 待支付状态
                'add_time[<]' => $expiredTime
            ]);
            
            if (empty($expiredOrders)) {
                $this->logger->debug('No expired orders found for cleanup.');
                return;
            }
            
            $this->logger->info('Found expired orders for cleanup.', [
                'count' => count($expiredOrders),
                'expired_before' => $expiredTime,
                'timeout_seconds' => $timeoutSeconds
            ]);
            
            // 删除过期订单
            $deletedCount = $this->db->delete('codepay_orders', [
                'status' => 0,
                'add_time[<]' => $expiredTime
            ]);
            
            $this->logger->info('Expired orders cleanup completed.', [
                'deleted_count' => $deletedCount,
                'expired_time_threshold' => $expiredTime
            ]);
            
            // 记录被删除的订单详情（用于调试）
            foreach ($expiredOrders as $order) {
                $this->logger->debug('Expired order deleted.', [
                    'order_id' => $order['id'],
                    'out_trade_no' => $order['out_trade_no'],
                    'created_time' => $order['add_time'],
                    'expired_seconds' => time() - strtotime($order['add_time'])
                ]);
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to cleanup expired orders.', [
                'error' => $e->getMessage(),
                'expired_time' => $expiredTime
            ]);
    }
    }


}