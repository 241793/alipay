<?php

namespace AliMPay\Core;

use AliMPay\Utils\Logger;

class BackgroundTaskManager
{
    private $lockFilePath;
    private $lockHandle; // 用于持有文件锁资源
    private $logger;

    public function __construct(string $lockFilePath)
    {
        $this->lockFilePath = $lockFilePath;
        $this->logger = Logger::getInstance();
        $this->lockHandle = null;
    }

    /**
     * 尝试以非阻塞方式获取锁
     *
     * @return bool 成功获取锁返回 true，否则返回 false
     */
    public function acquireLock(): bool
    {
        $this->lockHandle = fopen($this->lockFilePath, 'w');
        if ($this->lockHandle === false) {
            $this->logger->error('无法创建或打开锁文件', ['lock_file' => $this->lockFilePath]);
            return false;
        }

        // 尝试以非阻塞方式获取排它锁
        if (flock($this->lockHandle, LOCK_EX | LOCK_NB)) {
            $this->logger->info('后台任务锁已获取', ['lock_file' => $this->lockFilePath]);
            return true;
        }

        // 如果没有获取到锁，则关闭文件句柄
        $this->logger->warning('未能获取后台任务锁，可能已有其他进程在运行', ['lock_file' => $this->lockFilePath]);
        fclose($this->lockHandle);
        $this->lockHandle = null;
        return false;
    }

    /**
     * 释放锁
     */
    public function releaseLock(): void
    {
        if ($this->lockHandle) {
            flock($this->lockHandle, LOCK_UN); // 释放锁
            fclose($this->lockHandle); // 关闭文件句柄
            $this->lockHandle = null;
            $this->logger->info('后台任务锁已释放', ['lock_file' => $this->lockFilePath]);
        }
    }

    public function __destruct()
    {
        // 确保在对象销毁时自动释放锁
        $this->releaseLock();
    }
} 