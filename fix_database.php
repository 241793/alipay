<?php
/**
 * 数据库修复脚本
 * 为现有数据库添加payment_amount字段
 */

// 数据库文件路径
$dbFile = __DIR__ . '/data/codepay.db';

// 检查数据库文件是否存在
if (!file_exists($dbFile)) {
    echo "数据库文件不存在，系统将在首次使用时自动创建。\n";
    exit(0);
}

try {
    // 连接数据库
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 检查payment_amount字段是否存在
    $stmt = $pdo->prepare("PRAGMA table_info(codepay_orders)");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasPaymentAmount = false;
    foreach ($columns as $column) {
        if ($column['name'] === 'payment_amount') {
            $hasPaymentAmount = true;
            break;
        }
    }
    
    if (!$hasPaymentAmount) {
        // 添加payment_amount字段
        $pdo->exec("ALTER TABLE codepay_orders ADD COLUMN payment_amount DECIMAL(10, 2) DEFAULT 0");
        
        // 将现有订单的payment_amount设置为price的值
        $pdo->exec("UPDATE codepay_orders SET payment_amount = price WHERE payment_amount = 0");
        
        echo "✅ 成功添加payment_amount字段\n";
        echo "✅ 已将现有订单的payment_amount设置为price的值\n";
    } else {
        echo "ℹ️  payment_amount字段已存在，无需修复\n";
    }
    
    // 显示表结构
    echo "\n当前表结构:\n";
    foreach ($columns as $column) {
        echo "- {$column['name']}: {$column['type']}\n";
    }
    
    // 如果字段是新添加的，再次显示更新后的结构
    if (!$hasPaymentAmount) {
        echo "\n更新后的表结构:\n";
        $stmt = $pdo->prepare("PRAGMA table_info(codepay_orders)");
        $stmt->execute();
        $updatedColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($updatedColumns as $column) {
            echo "- {$column['name']}: {$column['type']}\n";
        }
    }
    
} catch (PDOException $e) {
    echo "❌ 数据库操作失败: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎉 数据库修复完成！现在可以正常使用支付功能了。\n";
?> 