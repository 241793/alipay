<?php

// Start output buffering to prevent header issues
ob_start();

// Set headers early to prevent header issues
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

require_once 'vendor/autoload.php';

use AliMPay\Core\CodePay;
use AliMPay\Utils\Logger;
use AliMPay\Core\BackgroundTaskManager;
use AliMPay\Core\PaymentMonitor;

/**
 * 改进的锁管理器 - 生产环境安全版本
 */
class ImprovedLockManager {
    private $lockFile;
    private $lockHandle;
    private $timeout;
    private $logger;
    private $maxRetries = 3;
    private static $lockCheckCache = [];
    private static $lastCacheTime = 0;
    private static $cacheTimeout = 5; // 5秒缓存
    
    public function __construct($lockFile, $timeout = 300) {
        $this->lockFile = $lockFile;
        $this->timeout = $timeout;
        $this->logger = Logger::getInstance();
        
        // 确保锁文件目录存在，使用更安全的权限
        $lockDir = dirname($lockFile);
        if (!is_dir($lockDir)) {
            mkdir($lockDir, 0750, true);
        }
    }
    
    /**
     * 尝试获取锁（非阻塞）- 增强安全版本
     */
    public function tryLock() {
        $retries = 0;
        
        while ($retries < $this->maxRetries) {
            try {
                // 检查是否有过期的锁
                $this->cleanupExpiredLock();
                
                // 尝试创建锁文件，使用更安全的模式
                $this->lockHandle = fopen($this->lockFile, 'c+');
                
                if (!$this->lockHandle) {
                    $retries++;
                    usleep(100000); // 等待100ms后重试
                    continue;
                }
                
                // 设置文件权限
                chmod($this->lockFile, 0640);
                
                // 使用文件锁（非阻塞）
                if (flock($this->lockHandle, LOCK_EX | LOCK_NB)) {
                    // 清空文件内容
                    ftruncate($this->lockHandle, 0);
                    rewind($this->lockHandle);
                    
                    // 写入锁信息（不包含敏感的PID信息）
                    $lockInfo = [
                        'timestamp' => time(),
                        'timeout' => $this->timeout,
                        'server_id' => substr(md5(gethostname() . getmypid()), 0, 8)
                    ];
                    
                    fwrite($this->lockHandle, json_encode($lockInfo));
                    fflush($this->lockHandle);
                    
                    $this->logger->info('Lock acquired successfully', [
                        'file' => basename($this->lockFile),
                        'server_id' => $lockInfo['server_id']
                    ]);
                    return true;
                } else {
                    fclose($this->lockHandle);
                    $this->lockHandle = null;
                    return false;
                }
                
            } catch (Exception $e) {
                $this->logger->error('Failed to acquire lock', [
                    'error' => $e->getMessage(),
                    'retry' => $retries + 1
                ]);
                $this->cleanup();
                $retries++;
                
                if ($retries < $this->maxRetries) {
                    usleep(200000); // 等待200ms后重试
                }
            }
        }
        
        return false;
    }
    
    /**
     * 释放锁 - 增强安全版本
     */
    public function releaseLock() {
        try {
            if ($this->lockHandle && is_resource($this->lockHandle)) {
                flock($this->lockHandle, LOCK_UN);
                fclose($this->lockHandle);
                $this->lockHandle = null;
            }
            
            if (file_exists($this->lockFile)) {
                unlink($this->lockFile);
            }
            
            $this->logger->debug('Lock released successfully', ['file' => basename($this->lockFile)]);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to release lock', ['error' => $e->getMessage()]);
        }
    }
    
    /**
     * 清理过期的锁 - 增强版本
     */
    private function cleanupExpiredLock() {
        if (!file_exists($this->lockFile)) {
            return;
        }
        
        try {
            // 使用缓存避免频繁的文件操作
            $cacheKey = $this->lockFile;
            $currentTime = time();
            
            if (isset(self::$lockCheckCache[$cacheKey]) && 
                ($currentTime - self::$lastCacheTime) < self::$cacheTimeout) {
                return;
            }
            
            $lockContent = @file_get_contents($this->lockFile);
            if ($lockContent === false) {
                return;
            }
            
            $lockInfo = @json_decode($lockContent, true);
            
            if (!$lockInfo || !isset($lockInfo['timestamp'], $lockInfo['timeout'])) {
                // 无效的锁文件，直接删除
                @unlink($this->lockFile);
                $this->logger->info('Removed invalid lock file', ['file' => basename($this->lockFile)]);
                return;
            }
            
            $lockAge = $currentTime - $lockInfo['timestamp'];
            
            // 检查锁是否过期
            if ($lockAge > $lockInfo['timeout']) {
                // 尝试获取文件锁来确保安全删除
                $testHandle = @fopen($this->lockFile, 'r+');
                if ($testHandle) {
                    if (flock($testHandle, LOCK_EX | LOCK_NB)) {
                        flock($testHandle, LOCK_UN);
                        fclose($testHandle);
                        @unlink($this->lockFile);
                        $this->logger->info('Removed expired lock file', [
                            'file' => basename($this->lockFile),
                            'age' => $lockAge
                        ]);
                    } else {
                        fclose($testHandle);
                    }
                }
            }
            
            // 更新缓存
            self::$lockCheckCache[$cacheKey] = $currentTime;
            self::$lastCacheTime = $currentTime;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to cleanup expired lock', ['error' => $e->getMessage()]);
        }
    }
    
    /**
     * 清理资源
     */
    private function cleanup() {
        if ($this->lockHandle && is_resource($this->lockHandle)) {
            @fclose($this->lockHandle);
            $this->lockHandle = null;
        }
    }
    
    /**
     * 析构函数，确保锁被释放
     */
    public function __destruct() {
        $this->releaseLock();
    }
}

/**
 * 改进的监控服务启动器 - 生产环境优化版本
 */
function startMonitoringServiceIfNeeded() {
    static $lastCheck = 0;
    static $checkInterval = 30; // 30秒内不重复检查
    
    $currentTime = time();
    $logger = Logger::getInstance();
    
    // 记录调用信息
    $logger->debug('startMonitoringServiceIfNeeded called', [
        'current_time' => $currentTime,
        'last_check' => $lastCheck,
        'time_since_last_check' => $currentTime - $lastCheck
    ]);
    
    // 检查是否是强制触发
    $forceStart = isset($_GET['internal_trigger']) && $_GET['internal_trigger'] === 'force_start';
    
    // 避免频繁检查，减少性能影响（除非强制启动）
    if (!$forceStart && $currentTime - $lastCheck < $checkInterval) {
        $logger->debug('Skipping monitoring check due to interval limit');
        return;
    }
    
    $lastCheck = $currentTime;
    
    $lockFile = __DIR__ . '/data/monitor.lock';
    $monitorInterval = 300; // 5分钟锁定时间
    
    $lockManager = new ImprovedLockManager($lockFile, $monitorInterval);
    
    // 使用非阻塞锁，避免API请求被阻塞
    if (!$lockManager->tryLock()) {
        // 无法获取锁，说明监控服务已经在运行
        $logger->debug('Monitoring service already running, skipping');
        
        // 即使无法获取锁，也更新一个"尝试运行"的状态
        updateMonitorStatus('attempted', 'Monitoring already running, lock not acquired');
        return;
    }
    
    try {
        $logger->info('Container monitoring service triggered by API request');

        // 增加配置文件存在性检查
        $configFile = __DIR__ . '/config/alipay.php';
        if (!file_exists($configFile)) {
            throw new Exception('Alipay configuration file not found');
        }
        
        $alipayConfig = require $configFile;
        $billQuery = new \AliMPay\Core\BillQuery(new \AliMPay\Core\AlipayClient($alipayConfig));
        $logger->info('Alipay client for container monitoring initialized');
        
        $codePay = new CodePay();
        $db = $codePay->getDb();
        $merchantConfig = $codePay->getMerchantInfo();

        $paymentMonitor = new PaymentMonitor($billQuery, $db, $merchantConfig);
        $paymentMonitor->runMonitoringCycle();
        $logger->info('Container monitoring cycle completed successfully');
        
        // 更新监控状态文件，供health.php检查
        updateMonitorStatus('completed', 'API triggered monitoring cycle completed successfully');
        
        // 在后台异步启动持续监控
        startBackgroundMonitoring();
        
    } catch (Exception $e) {
        $logger->error('Container monitoring failed', ['error' => $e->getMessage()]);
        
        // 更新监控状态文件记录错误
        updateMonitorStatus('error', $e->getMessage());
    } finally {
        $lockManager->releaseLock();
    }
}

/**
 * 改进的后台监控启动 - 生产环境安全版本
 */
function startBackgroundMonitoring() {
    $backgroundScript = __DIR__ . '/container_monitor.php';
    $lockFile = __DIR__ . '/data/container_monitor.lock';
    
    // 检查后台监控是否已经在运行
    $backgroundLock = new ImprovedLockManager($lockFile, 3600); // 1小时超时
    
    if (!$backgroundLock->tryLock()) {
        Logger::getInstance()->debug('Background monitoring already running');
        return;
    }
    
    // 立即释放锁，让后台脚本自己管理
    $backgroundLock->releaseLock();
    
    // 检查后台监控脚本是否存在
    if (!file_exists($backgroundScript)) {
        createBackgroundMonitorScript($backgroundScript);
    }
    
    // 验证脚本文件完整性
    if (filesize($backgroundScript) < 1000) { // 基本的文件大小检查
        Logger::getInstance()->warning('Background script seems incomplete, recreating');
        createBackgroundMonitorScript($backgroundScript);
    }
    
    try {
        // 在后台启动监控脚本，增加错误处理
        if (PHP_OS_FAMILY !== 'Windows') {
            // Linux/Unix 环境 - 使用更安全的方式
            $command = sprintf(
                'nohup %s %s > /dev/null 2>&1 & echo $!',
                escapeshellarg(PHP_BINARY),
                escapeshellarg($backgroundScript)
            );
        } else {
            // Windows 环境
            $command = sprintf(
                'start /B %s %s',
                escapeshellarg(PHP_BINARY),
                escapeshellarg($backgroundScript)
            );
        }
        
        $pid = exec($command);
        
        Logger::getInstance()->info('Background monitoring process started', [
            'script' => basename($backgroundScript),
            'pid' => $pid
        ]);
        
    } catch (Exception $e) {
        Logger::getInstance()->error('Failed to start background monitoring', [
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * 创建改进的后台监控脚本
 */
function createBackgroundMonitorScript($scriptPath) {
    $script = '<?php
require_once __DIR__ . \'/vendor/autoload.php\';

use AliMPay\Core\AlipayClient;
use AliMPay\Core\BillQuery;
use AliMPay\Core\PaymentMonitor;
use AliMPay\Core\CodePay;
use AliMPay\Utils\Logger;

// 设置北京时间
date_default_timezone_set(\'Asia/Shanghai\');

$logger = Logger::getInstance();
$logger->info("Container background monitor started");

$lockFile = __DIR__ . \'/data/container_monitor.lock\';
$monitorInterval = 30; // 30秒间隔
$maxRunTime = 3600; // 最多运行1小时后自动退出，让API重新启动

// 使用改进的锁管理器
class ImprovedLockManager {
    private $lockFile;
    private $lockHandle;
    private $timeout;
    private $logger;
    
    public function __construct($lockFile, $timeout = 300) {
        $this->lockFile = $lockFile;
        $this->timeout = $timeout;
        $this->logger = Logger::getInstance();
        
        $lockDir = dirname($lockFile);
        if (!is_dir($lockDir)) {
            mkdir($lockDir, 0755, true);
        }
    }
    
    public function tryLock() {
        try {
            $this->lockHandle = fopen($this->lockFile, \'w\');
            
            if (!$this->lockHandle) {
                return false;
            }
            
            if (flock($this->lockHandle, LOCK_EX | LOCK_NB)) {
                $lockInfo = [
                    \'pid\' => getmypid(),
                    \'timestamp\' => time(),
                    \'timeout\' => $this->timeout
                ];
                
                fwrite($this->lockHandle, json_encode($lockInfo));
                fflush($this->lockHandle);
                
                return true;
            } else {
                fclose($this->lockHandle);
                $this->lockHandle = null;
                return false;
            }
            
        } catch (Exception $e) {
            $this->logger->error(\'Failed to acquire background lock\', [\'error\' => $e->getMessage()]);
            return false;
        }
    }
    
    public function releaseLock() {
        try {
            if ($this->lockHandle) {
                flock($this->lockHandle, LOCK_UN);
                fclose($this->lockHandle);
                $this->lockHandle = null;
            }
            
            if (file_exists($this->lockFile)) {
                unlink($this->lockFile);
            }
            
        } catch (Exception $e) {
            $this->logger->error(\'Failed to release background lock\', [\'error\' => $e->getMessage()]);
        }
    }
    
    public function __destruct() {
        $this->releaseLock();
    }
}

$lockManager = new ImprovedLockManager($lockFile, $maxRunTime);

if (!$lockManager->tryLock()) {
    $logger->info("Another background monitor is already running");
    exit(0);
}

// 注册清理函数
register_shutdown_function(function() use ($lockManager) {
    $lockManager->releaseLock();
});

$startTime = time();
$cycleCount = 0;

try {
    $alipayClient = new AlipayClient();
    $billQuery = new BillQuery($alipayClient);
    $codePay = new CodePay();
    $db = $codePay->getDb();
    $merchantInfo = $codePay->getMerchantInfo();
    
    $paymentMonitor = new PaymentMonitor($billQuery, $db, $merchantInfo);
    
    while (true) {
        $currentTime = time();
        $runTime = $currentTime - $startTime;
        
        // 如果运行时间超过最大时间，退出让API重新启动
        if ($runTime >= $maxRunTime) {
            $logger->info("Container monitor reached max runtime, exiting gracefully", [
                "run_time" => $runTime,
                "cycles_completed" => $cycleCount
            ]);
            break;
        }
        
        $cycleCount++;
        
        try {
            $paymentMonitor->runMonitoringCycle();
            $logger->debug("Container monitor cycle completed", ["cycle" => $cycleCount]);
        } catch (Exception $e) {
            $logger->error("Container monitor cycle failed", [
                "cycle" => $cycleCount,
                "error" => $e->getMessage()
            ]);
        }
        
        sleep($monitorInterval);
    }
    
} catch (Exception $e) {
    $logger->error("Container monitor initialization failed", ["error" => $e->getMessage()]);
}

$lockManager->releaseLock();
$logger->info("Container background monitor stopped", ["total_cycles" => $cycleCount]);
?>';
    
    file_put_contents($scriptPath, $script);
    Logger::getInstance()->info('Container monitor script created', ['path' => $scriptPath]);
}

/**
 * 更新监控状态文件
 */
function updateMonitorStatus($status, $message = '') {
    try {
        $statusFile = __DIR__ . '/data/monitor_status.json';
        
        // 确保目录存在
        $statusDir = dirname($statusFile);
        if (!is_dir($statusDir)) {
            if (!mkdir($statusDir, 0750, true)) {
                throw new Exception("Failed to create status directory: $statusDir");
            }
        }
        
        // 检查目录权限
        if (!is_writable($statusDir)) {
            throw new Exception("Status directory is not writable: $statusDir");
        }
        
        $statusData = [
            'last_run' => time(),
            'last_run_formatted' => date('Y-m-d H:i:s'),
            'status' => $status,
            'message' => $message,
            'updated_by' => 'api_request',
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true)
        ];
        
        if ($status === 'completed') {
            $statusData['last_success'] = time();
            $statusData['last_success_formatted'] = date('Y-m-d H:i:s');
        } elseif ($status === 'error') {
            $statusData['last_error'] = $message;
            $statusData['last_error_time'] = time();
        }
        
        $jsonData = json_encode($statusData, JSON_PRETTY_PRINT);
        if ($jsonData === false) {
            throw new Exception('Failed to encode status data to JSON');
        }
        
        $result = file_put_contents($statusFile, $jsonData, LOCK_EX);
        if ($result === false) {
            throw new Exception("Failed to write status file: $statusFile");
        }
        
        // 设置文件权限
        chmod($statusFile, 0640);
        
        Logger::getInstance()->info('Monitor status updated successfully', [
            'file' => $statusFile,
            'status' => $status,
            'bytes_written' => $result
        ]);
        
    } catch (Exception $e) {
        Logger::getInstance()->error('Failed to update monitor status', [
            'error' => $e->getMessage(),
            'status_file' => $statusFile ?? 'unknown',
            'attempted_status' => $status
        ]);
        
        // 尝试创建一个简单的状态文件
        try {
            $fallbackFile = __DIR__ . '/monitor_status_fallback.txt';
            $fallbackData = date('Y-m-d H:i:s') . " - Status: $status - Message: $message\n";
            file_put_contents($fallbackFile, $fallbackData, FILE_APPEND | LOCK_EX);
        } catch (Exception $fe) {
            // 最后的备选方案失败，记录到日志
            Logger::getInstance()->critical('All status update methods failed', [
                'original_error' => $e->getMessage(),
                'fallback_error' => $fe->getMessage()
            ]);
        }
    }
}

/**
 * Trigger an on-demand monitoring cycle for real-time order status refresh.
 * This is used by the payment page polling path when an order is still pending.
 */
function triggerRealtimeMonitoringForOrder($pid, $outTradeNo) {
    $logger = Logger::getInstance();
    $lockFile = __DIR__ . '/data/realtime_monitor.lock';
    $throttleFile = __DIR__ . '/data/realtime_monitor_throttle.json';
    $minInterval = 2;
    $now = time();

    // Global throttle to avoid running a full cycle on every poll request.
    if (file_exists($throttleFile)) {
        $raw = @file_get_contents($throttleFile);
        $info = $raw ? @json_decode($raw, true) : null;
        $lastRun = (int)($info['last_run'] ?? 0);
        if ($lastRun > 0 && ($now - $lastRun) < $minInterval) {
            return;
        }
    }

    $lockManager = new ImprovedLockManager($lockFile, 20);
    if (!$lockManager->tryLock()) {
        return;
    }

    try {
        @file_put_contents($throttleFile, json_encode([
            'last_run' => $now,
            'last_run_formatted' => date('Y-m-d H:i:s'),
            'trigger' => 'order_poll',
            'pid' => $pid,
            'out_trade_no' => $outTradeNo
        ], JSON_UNESCAPED_UNICODE), LOCK_EX);

        $configFile = __DIR__ . '/config/alipay.php';
        if (!file_exists($configFile)) {
            return;
        }

        $alipayConfig = require $configFile;
        $billQuery = new \AliMPay\Core\BillQuery(new \AliMPay\Core\AlipayClient($alipayConfig));

        $codePay = new CodePay();
        $db = $codePay->getDb();
        $merchantConfig = $codePay->getMerchantInfo();
        $paymentMonitor = new PaymentMonitor($billQuery, $db, $merchantConfig);

        $logger->debug('Running realtime monitoring cycle for pending order', [
            'pid' => $pid,
            'out_trade_no' => $outTradeNo
        ]);
        $paymentMonitor->runMonitoringCycle();
    } catch (Exception $e) {
        $logger->warning('Realtime monitoring cycle failed', [
            'error' => $e->getMessage(),
            'out_trade_no' => $outTradeNo
        ]);
    } finally {
        $lockManager->releaseLock();
    }
}

function redirectToSubmitPage(array $paymentResult) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>正在跳转到支付页面...</title>
        <meta charset="utf-8">
        <style>
            body {
                font-family: sans-serif;
                text-align: center;
                padding-top: 50px;
                background-color: #f5f5f5;
            }
            .loading-spinner {
                border: 4px solid #f3f3f3;
                border-top: 4px solid #3498db;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                animation: spin 1s linear infinite;
                margin: 20px auto;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
    </head>
    <body onload="document.getElementById('redirectForm').submit();">
        <form id="redirectForm" method="post" action="submit.php">
            <input type="hidden" name="payment_result" value="<?php echo htmlspecialchars(json_encode($paymentResult)); ?>">
            <?php foreach ($paymentResult as $key => $value): ?>
                <?php if (!is_array($value)): ?>
                    <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
                <?php endif; ?>
            <?php endforeach; ?>
        </form>
        <h2>正在为您跳转到支付页面，请稍候...</h2>
        <div class="loading-spinner"></div>
        <p>如果您的浏览器没有自动跳转，请点击下方的按钮</p>
        <button onclick="document.getElementById('redirectForm').submit();">继续</button>
    </body>
    </html>
    <?php
    exit;
}

// 在每次API请求时触发监控检查
startMonitoringServiceIfNeeded();

try {
    $codePay = new CodePay();
    $logger = Logger::getInstance();
    
    // Get action parameter (CodePay protocol), compatible with 'act' and 'action'
    $action = $_GET['act'] ?? $_GET['action'] ?? $_POST['act'] ?? $_POST['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // 如果是创建支付的请求，并且没有指定输出格式，则重定向到submit.php
    $isPaymentRequest = ($method === 'POST' && empty($action)) || in_array($action, ['submit', 'create']);
    $outputFormat = $_GET['format'] ?? $_POST['format'] ?? 'html';

    if ($isPaymentRequest && $outputFormat !== 'json') {
        $params = [
            'pid' => $_POST['pid'] ?? '',
            'type' => $_POST['type'] ?? '',
            'out_trade_no' => $_POST['out_trade_no'] ?? '',
            'notify_url' => $_POST['notify_url'] ?? '',
            'return_url' => $_POST['return_url'] ?? '',
            'name' => $_POST['name'] ?? '',
            'money' => $_POST['money'] ?? '',
            'sitename' => $_POST['sitename'] ?? '',
            'sign' => $_POST['sign'] ?? '',
            'sign_type' => $_POST['sign_type'] ?? 'MD5'
        ];
        
        $paymentResult = $codePay->createPayment($params);
        redirectToSubmitPage($paymentResult);
        exit;
    }

    if (empty($action)) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['code' => -1, 'msg' => 'Missing action parameter'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $logger->info('CodePay API Request', ['action' => $action, 'method' => $method, 'ip' => $ip]);
    
    switch ($action) {
        case 'query':
            // Query merchant information - CodePay protocol
            $pid = $_GET['pid'] ?? $_POST['pid'] ?? '';
            $key = $_GET['key'] ?? $_POST['key'] ?? '';
            
            if (empty($pid) || empty($key)) {
                ob_end_clean();
                http_response_code(400);
                echo json_encode([
                    'code' => -1,
                    'msg' => 'Missing required parameters: pid, key'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $result = $codePay->queryMerchant($pid, $key);
            break;
            
        case 'order':
            // Query single order - CodePay protocol
            $pid = $_GET['pid'] ?? $_POST['pid'] ?? '';
            $key = $_GET['key'] ?? $_POST['key'] ?? '';
            $outTradeNo = $_GET['out_trade_no'] ?? $_POST['out_trade_no'] ?? '';
            
            if (empty($pid) || empty($outTradeNo)) {
                ob_end_clean();
                http_response_code(400);
                echo json_encode([
                    'code' => -1,
                    'msg' => 'Missing required parameters for order query'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // Allow keyless query for frontend status check
            $validateKey = !empty($key);
            $result = $codePay->queryOrder($pid, $key, $outTradeNo, $validateKey);

            // Frontend polling enhancement: if still pending, run one on-demand cycle and re-check.
            if (
                !$validateKey &&
                isset($result['code'], $result['status']) &&
                (int)$result['code'] === 1 &&
                (int)$result['status'] === 0
            ) {
                triggerRealtimeMonitoringForOrder($pid, $outTradeNo);
                $result = $codePay->queryOrder($pid, $key, $outTradeNo, $validateKey);
            }
            break;

        case 'orders':
            // Query multiple orders - CodePay protocol
            $pid = $_GET['pid'] ?? $_POST['pid'] ?? '';
            $key = $_GET['key'] ?? $_POST['key'] ?? '';
            $limit = (int)($_GET['limit'] ?? $_POST['limit'] ?? 20);
            
            if (empty($pid) || empty($key)) {
                ob_end_clean();
                http_response_code(400);
                echo json_encode([
                    'code' => -1,
                    'msg' => 'Missing required parameters: pid, key'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $result = $codePay->queryOrders($pid, $key, $limit);
            break;
            
        case 'submit':
        case 'create':
            // Create payment request - CodePay protocol
            $params = [
                'pid' => $_POST['pid'] ?? '',
                'type' => $_POST['type'] ?? '',
                'out_trade_no' => $_POST['out_trade_no'] ?? '',
                'notify_url' => $_POST['notify_url'] ?? '',
                'return_url' => $_POST['return_url'] ?? '',
                'name' => $_POST['name'] ?? '',
                'money' => $_POST['money'] ?? '',
                'sitename' => $_POST['sitename'] ?? '',
                'sign' => $_POST['sign'] ?? '',
                'sign_type' => $_POST['sign_type'] ?? 'MD5'
            ];
            
            $result = $codePay->createPayment($params);
            break;
            
        case 'notify':
            // Process payment notification - CodePay protocol
            $params = $_POST;
            $result = $codePay->processNotification($params);
            
            // The response for notification should be plain text "success" or "fail"
            ob_end_clean();
            if ($result['code'] === 1) {
                echo 'success';
            } else {
                echo 'fail';
            }
            exit;

        case 'health':
            // Health check
            $result = [
                'code' => 1,
                'msg' => 'System is healthy',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            break;

        default:
            ob_end_clean();
            http_response_code(400);
            echo json_encode([
                'code' => -1,
                'msg' => 'Invalid action'
            ], JSON_UNESCAPED_UNICODE);
            exit;
    }
    
    ob_end_clean();
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    if (isset($logger)) {
        $logger->error('CodePay API Error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
    
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'code' => -1,
        'msg' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
