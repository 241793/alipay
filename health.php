<?php
require_once 'vendor/autoload.php';

use AliMPay\Core\CodePay;
use AliMPay\Utils\Logger;
use AliMPay\Core\AlipayClient;
use AliMPay\Core\BillQuery;
use AliMPay\Core\PaymentMonitor;

// 设置北京时间
date_default_timezone_set('Asia/Shanghai');

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$action = $_GET['action'] ?? 'status';

try {
    switch ($action) {
        case 'status':
            checkSystemStatus();
            break;
        case 'monitor':
            runMonitoringCheck();
            break;
        case 'force-start':
            forceStartMonitoring();
            break;
        case 'cleanup':
            cleanupServices();
            break;
        case 'debug':
            debugMonitoringStatus();
            break;
        case 'trigger':
            triggerMonitoringFromHealth();
            break;
        default:
            respondError('Invalid action');
    }
} catch (Exception $e) {
    respondError($e->getMessage());
}

/**
 * 检查系统整体状态
 */
function checkSystemStatus() {
    $status = [
        'timestamp' => date('Y-m-d H:i:s'),
        'system' => 'CodePay Container Monitor',
        'status' => 'ok',
        'services' => [],
        'counters' => [],
        'suggestions' => []
    ];
    
    try {
        // 1. 检查数据库
        $codePay = new CodePay();
        $db = $codePay->getDb();
        $orderCount = $db->count('codepay_orders');
        $unpaidCount = $db->count('codepay_orders', ['status' => 0]);
        
        $status['services']['database'] = [
            'status' => 'healthy',
            'total_orders' => $orderCount,
            'unpaid_orders' => $unpaidCount
        ];
        
        // 2. 检查支付宝API
        $alipayClient = new AlipayClient();
        $alipayStatus = $alipayClient->validateConfig() ? 'healthy' : 'error';
        $status['services']['alipay_api'] = ['status' => $alipayStatus];
        
        // 3. 检查监控服务 - 使用改进的检测逻辑
        $monitorStatus = checkMonitoringServiceImproved();
        $status['services']['monitoring'] = $monitorStatus;
        
        // 4. 检查订单清理功能
        $config = require __DIR__ . '/config/alipay.php';
        $autoCleanup = $config['payment']['auto_cleanup'] ?? true;
        $orderTimeout = $config['payment']['order_timeout'] ?? 300;
        
        // 查询即将过期的订单数量
        $expiredThreshold = date('Y-m-d H:i:s', time() - $orderTimeout);
        $expiredCount = $db->count('codepay_orders', [
            'status' => 0,
            'add_time[<]' => $expiredThreshold
        ]);
        
        $status['services']['order_cleanup'] = [
            'status' => $autoCleanup ? 'enabled' : 'disabled',
            'timeout_seconds' => $orderTimeout,
            'expired_orders_count' => $expiredCount,
            'last_cleanup' => 'Runs with monitoring cycle'
        ];
        
        // 5. 统计信息
        $status['counters'] = [
            'total_orders' => $orderCount,
            'unpaid_orders' => $unpaidCount,
            'paid_orders' => $orderCount - $unpaidCount,
            'system_uptime' => getSystemUptime()
        ];
        
        // 6. 智能建议 - 改进的逻辑
        $suggestions = generateSmartSuggestions($monitorStatus, $unpaidCount, $expiredCount, $autoCleanup);
        $status['suggestions'] = $suggestions;
        
        // 7. 整体状态判断
        if ($alipayStatus !== 'healthy' || $monitorStatus['status'] === 'error') {
            $status['status'] = 'degraded';
        } elseif ($monitorStatus['status'] === 'healthy' && $unpaidCount < 20) {
            $status['status'] = 'excellent';
        }
        
    } catch (Exception $e) {
        $status['status'] = 'error';
        $status['error'] = $e->getMessage();
    }
    
    respondSuccess($status);
}

/**
 * 改进的监控服务状态检查
 */
function checkMonitoringServiceImproved() {
    $status = [
        'status' => 'unknown',
        'last_run' => null,
        'uptime' => 0,
        'health_score' => 0
    ];
    
    // 1. 首先检查状态文件（最可靠的指标）
    $statusFile = __DIR__ . '/data/monitor_status.json';
    if (file_exists($statusFile)) {
        $statusData = json_decode(file_get_contents($statusFile), true);
        if ($statusData && isset($statusData['last_run'])) {
            $timeSinceLastRun = time() - $statusData['last_run'];
            $status['last_run'] = $statusData['last_run_formatted'] ?? date('Y-m-d H:i:s', $statusData['last_run']);
            $status['seconds_since_last_run'] = $timeSinceLastRun;
            $status['last_message'] = $statusData['message'] ?? '';
            
            // 根据最后运行时间判断状态
            if ($timeSinceLastRun < 120) { // 2分钟内
                $status['status'] = 'healthy';
                $status['health_score'] = 100;
            } elseif ($timeSinceLastRun < 600) { // 10分钟内
                $status['status'] = 'running';
                $status['health_score'] = 75;
            } else {
                $status['status'] = 'stale';
                $status['health_score'] = 25;
            }
            
            // 检查是否有错误
            if (isset($statusData['last_error']) && $statusData['status'] === 'error') {
                $status['status'] = 'error';
                $status['last_error'] = $statusData['last_error'];
                $status['health_score'] = 0;
            }
        }
    }
    
    // 2. 检查锁文件作为辅助指标
    $lockFile = __DIR__ . '/data/monitor.lock';
    $containerLockFile = __DIR__ . '/data/container_monitor.lock';
    
    $activeLocks = 0;
    
    if (file_exists($lockFile)) {
        $lockContent = @file_get_contents($lockFile);
        if ($lockContent) {
            $lockInfo = @json_decode($lockContent, true);
            if ($lockInfo && isset($lockInfo['timestamp'])) {
                $lockAge = time() - $lockInfo['timestamp'];
                if ($lockAge < 300) { // 5分钟内的锁认为是活跃的
                    $activeLocks++;
                    $status['monitor_lock'] = 'active';
                }
            }
        }
    }
    
    if (file_exists($containerLockFile)) {
        $lockContent = @file_get_contents($containerLockFile);
        if ($lockContent) {
            $lockInfo = @json_decode($lockContent, true);
            if ($lockInfo && isset($lockInfo['timestamp'])) {
                $lockAge = time() - $lockInfo['timestamp'];
                if ($lockAge < 3600) { // 1小时内的锁认为是活跃的
                    $activeLocks++;
                    $status['container_lock'] = 'active';
                    $status['uptime'] = $lockAge;
                }
            }
        }
    }
    
    // 3. 综合判断
    if ($activeLocks > 0 && $status['status'] === 'unknown') {
        $status['status'] = 'running';
        $status['health_score'] = 60;
    }
    
    // 4. 如果所有检查都失败，尝试运行一次测试
    if ($status['status'] === 'unknown') {
        $testResult = testMonitoringFunctionality();
        if ($testResult['success']) {
            $status['status'] = 'dormant'; // 功能正常但未主动运行
            $status['health_score'] = 40;
            $status['test_result'] = 'Monitoring functions are operational';
        } else {
            $status['status'] = 'error';
            $status['health_score'] = 0;
            $status['test_error'] = $testResult['error'];
        }
    }
    
    return $status;
}

/**
 * 测试监控功能
 */
function testMonitoringFunctionality() {
    try {
        $codePay = new CodePay();
        $db = $codePay->getDb();
        
        // 简单的功能测试
        $alipayClient = new AlipayClient();
        $isConfigValid = $alipayClient->validateConfig();
        
        if (!$isConfigValid) {
            return ['success' => false, 'error' => 'Alipay configuration is invalid'];
        }
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * 生成智能建议
 */
function generateSmartSuggestions($monitorStatus, $unpaidCount, $expiredCount, $autoCleanup) {
    $suggestions = [];
    
    // 监控服务建议
    switch ($monitorStatus['status']) {
        case 'error':
            $suggestions[] = "❌ Monitoring service has errors. Check logs and call /health.php?action=force-start to restart.";
            break;
        case 'unknown':
        case 'dormant':
            $suggestions[] = "⚠️ Monitoring service is not active. Call /health.php?action=force-start to start it.";
            break;
        case 'stale':
            $suggestions[] = "⏰ Monitoring service is stale (last run: {$monitorStatus['last_run']}). Consider restarting.";
            break;
        case 'running':
            if ($monitorStatus['health_score'] < 80) {
                $suggestions[] = "📊 Monitoring service is running but health score is low ({$monitorStatus['health_score']}%).";
            }
            break;
        case 'healthy':
            // 无需建议，运行正常
            break;
    }
    
    // 订单相关建议
    if ($unpaidCount > 20) {
        $suggestions[] = "💰 High number of unpaid orders ({$unpaidCount}). Verify payment processing is working correctly.";
    } elseif ($unpaidCount > 50) {
        $suggestions[] = "🚨 Very high number of unpaid orders ({$unpaidCount}). Immediate attention required!";
    }
    
    // 清理建议
    if (!$autoCleanup && $expiredCount > 0) {
        $suggestions[] = "🧹 Auto cleanup is disabled and there are {$expiredCount} expired orders. Consider enabling auto cleanup.";
    } elseif ($expiredCount > 10) {
        $suggestions[] = "⚡ High number of expired orders ({$expiredCount}). Monitor service might need optimization.";
    }
    
    // 性能建议
    if ($monitorStatus['health_score'] === 100 && $unpaidCount < 5 && $expiredCount < 2) {
        $suggestions[] = "✅ System is performing excellently! All services are healthy.";
    }
    
    return $suggestions;
}

/**
 * 运行监控检查
 */
function runMonitoringCheck() {
    $result = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => 'monitoring_check',
        'status' => 'completed'
    ];
    
    try {
        $codePay = new CodePay();
        $db = $codePay->getDb();
        $merchantInfo = $codePay->getMerchantInfo();
        
        $alipayClient = new AlipayClient();
        $billQuery = new BillQuery($alipayClient);
        $paymentMonitor = new PaymentMonitor($billQuery, $db, $merchantInfo);
        
        // 运行一次监控周期
        $paymentMonitor->runMonitoringCycle();
        
        $result['message'] = 'Monitoring cycle completed successfully';
        
        // 更新监控状态文件
        updateMonitorStatusFile('completed', 'Manual monitoring check completed successfully');
        
    } catch (Exception $e) {
        $result['status'] = 'error';
        $result['error'] = $e->getMessage();
        
        // 记录错误状态
        updateMonitorStatusFile('error', $e->getMessage());
    }
    
    respondSuccess($result);
}

/**
 * 调试监控状态
 */
function debugMonitoringStatus() {
    $debug = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => 'debug_monitoring',
        'file_checks' => [],
        'directory_checks' => [],
        'api_trigger_test' => []
    ];
    
    // 检查关键文件和目录
    $pathsToCheck = [
        'data_dir' => __DIR__ . '/data',
        'status_file' => __DIR__ . '/data/monitor_status.json',
        'monitor_lock' => __DIR__ . '/data/monitor.lock',
        'container_lock' => __DIR__ . '/data/container_monitor.lock',
        'api_file' => __DIR__ . '/api.php',
        'fallback_file' => __DIR__ . '/monitor_status_fallback.txt'
    ];
    
    foreach ($pathsToCheck as $name => $path) {
        $info = [
            'path' => $path,
            'exists' => file_exists($path),
            'is_file' => is_file($path),
            'is_dir' => is_dir($path),
            'readable' => is_readable($path),
            'writable' => is_writable($path)
        ];
        
        if ($info['exists'] && $info['is_file']) {
            $info['size'] = filesize($path);
            $info['modified'] = date('Y-m-d H:i:s', filemtime($path));
            
            if ($name === 'status_file' && $info['readable']) {
                $content = file_get_contents($path);
                $info['content'] = json_decode($content, true);
            }
        }
        
        $debug['file_checks'][$name] = $info;
    }
    
    // 测试目录创建
    $testDir = __DIR__ . '/data_test';
    try {
        if (!is_dir($testDir)) {
            mkdir($testDir, 0750, true);
            $debug['directory_checks']['test_create'] = 'success';
            rmdir($testDir);
        } else {
            $debug['directory_checks']['test_create'] = 'directory_exists';
        }
    } catch (Exception $e) {
        $debug['directory_checks']['test_create'] = 'failed: ' . $e->getMessage();
    }
    
    // 测试API触发
    try {
        // 模拟API调用
        $_GET['internal_trigger'] = 'debug_test';
        
        $codePay = new CodePay();
        $debug['api_trigger_test']['codepay_init'] = 'success';
        
        $alipayClient = new AlipayClient();
        $debug['api_trigger_test']['alipay_init'] = 'success';
        
        $debug['api_trigger_test']['status'] = 'api_components_functional';
        
    } catch (Exception $e) {
        $debug['api_trigger_test']['error'] = $e->getMessage();
    }
    
    respondSuccess($debug);
}

/**
 * 从Health页面触发监控
 */
function triggerMonitoringFromHealth() {
    $result = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => 'trigger_monitoring_from_health'
    ];
    
    try {
        // 直接调用监控逻辑，不依赖API文件
        $codePay = new CodePay();
        $db = $codePay->getDb();
        $merchantInfo = $codePay->getMerchantInfo();
        
        $alipayClient = new AlipayClient();
        $billQuery = new BillQuery($alipayClient);
        $paymentMonitor = new PaymentMonitor($billQuery, $db, $merchantInfo);
        
        // 运行监控周期
        $paymentMonitor->runMonitoringCycle();
        
        // 直接更新状态文件
        updateMonitorStatusFile('completed', 'Triggered directly from health check');
        
        $result['status'] = 'completed';
        $result['message'] = 'Monitoring triggered and completed successfully from health check';
        
    } catch (Exception $e) {
        updateMonitorStatusFile('error', 'Health trigger failed: ' . $e->getMessage());
        $result['status'] = 'error';
        $result['error'] = $e->getMessage();
    }
    
    respondSuccess($result);
}

/**
 * 更新监控状态文件
 */
function updateMonitorStatusFile($status, $message) {
    try {
        $statusFile = __DIR__ . '/data/monitor_status.json';
        
        // 确保目录存在
        $statusDir = dirname($statusFile);
        if (!is_dir($statusDir)) {
            mkdir($statusDir, 0750, true);
        }
        
        $statusData = [
            'last_run' => time(),
            'last_run_formatted' => date('Y-m-d H:i:s'),
            'status' => $status,
            'message' => $message,
            'updated_by' => 'health_check'
        ];
        
        if ($status === 'completed') {
            $statusData['last_success'] = time();
            $statusData['last_success_formatted'] = date('Y-m-d H:i:s');
        } elseif ($status === 'error') {
            $statusData['last_error'] = $message;
            $statusData['last_error_time'] = time();
        }
        
        file_put_contents($statusFile, json_encode($statusData, JSON_PRETTY_PRINT));
        
    } catch (Exception $e) {
        Logger::getInstance()->error('Failed to update monitor status', ['error' => $e->getMessage()]);
    }
}

/**
 * 强制启动监控服务
 */
function forceStartMonitoring() {
    $result = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => 'force_start_monitoring'
    ];
    
    try {
        // 清理旧的锁文件
        $lockFiles = [
            __DIR__ . '/data/monitor.lock',
            __DIR__ . '/data/container_monitor.lock'
        ];
        
        $cleanedFiles = [];
        foreach ($lockFiles as $lockFile) {
            if (file_exists($lockFile)) {
                unlink($lockFile);
                $cleanedFiles[] = basename($lockFile);
            }
        }
        
        // 触发API中的监控启动逻辑
        $apiFile = __DIR__ . '/api.php';
        if (file_exists($apiFile)) {
            // 通过内部调用触发监控
            $_GET['internal_trigger'] = 'force_start';
            include $apiFile;
        }
        
        $result['status'] = 'started';
        $result['message'] = 'Monitoring service force started';
        $result['cleaned_files'] = $cleanedFiles;
        
        // 更新状态文件
        updateMonitorStatusFile('started', 'Force started by health check');
        
    } catch (Exception $e) {
        $result['status'] = 'error';
        $result['error'] = $e->getMessage();
    }
    
    respondSuccess($result);
}

/**
 * 清理服务文件
 */
function cleanupServices() {
    $result = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => 'cleanup',
        'cleaned_files' => []
    ];
    
    $filesToClean = [
        __DIR__ . '/data/monitor.lock',
        __DIR__ . '/data/container_monitor.lock',
        __DIR__ . '/container_monitor.php'
    ];
    
    foreach ($filesToClean as $file) {
        if (file_exists($file)) {
            unlink($file);
            $result['cleaned_files'][] = basename($file);
        }
    }
    
    $result['status'] = 'completed';
    $result['message'] = 'Cleanup completed';
    
    respondSuccess($result);
}

/**
 * 获取系统运行时间
 */
function getSystemUptime() {
    $configFile = __DIR__ . '/config/codepay.json';
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        if (isset($config['created_at'])) {
            $startTime = strtotime($config['created_at']);
            return time() - $startTime;
        }
    }
    return 0;
}

/**
 * 成功响应
 */
function respondSuccess($data) {
    echo json_encode([
        'success' => true,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * 错误响应
 */
function respondError($message) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
?>
