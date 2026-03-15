<?php
/**
 * 二维码访问端点
 * 提供经营码二维码的HTTP访问
 */

// 设置正确的内容类型
header('Content-Type: image/png');
header('Cache-Control: public, max-age=3600'); // 缓存1小时

// 加载配置
$config = require __DIR__ . '/config/alipay.php';

// 获取二维码类型参数
$type = $_GET['type'] ?? 'business';
$token = $_GET['token'] ?? '';

// 验证token（简单的安全验证）
$expectedToken = md5('qrcode_access_' . date('Y-m-d'));
if ($token !== $expectedToken) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Invalid token';
    exit;
}

try {
    switch ($type) {
        case 'business':
            // 经营码二维码
            $qrCodePath = $config['payment']['business_qr_mode']['qr_code_path'];
            
            if (!file_exists($qrCodePath)) {
                // 如果经营码不存在，返回默认提示图片
                header('Content-Type: text/plain');
                echo '经营码二维码文件不存在，请先上传到: ' . $qrCodePath;
                exit;
            }
            
            // 读取并输出二维码文件
            $imageData = file_get_contents($qrCodePath);
            
            // 根据文件类型设置正确的Content-Type
            $imageInfo = getimagesizefromstring($imageData);
            if ($imageInfo) {
                header('Content-Type: ' . $imageInfo['mime']);
            }
            
            echo $imageData;
            break;
            
        default:
            header('HTTP/1.1 400 Bad Request');
            echo 'Invalid QR code type';
            break;
    }
    
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Error loading QR code: ' . $e->getMessage();
}
?> 