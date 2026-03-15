<?php

namespace AliMPay\Core;

use Alipay\OpenAPISDK\Util\Model\AlipayConfig;
use Alipay\OpenAPISDK\Util\AlipayConfigUtil;
use AliMPay\Utils\Logger;

class AlipayClient
{
    private $config;
    private $alipayConfig;
    private $alipayConfigUtil;
    private $logger;
    
    public function __construct(array $config = [])
    {
        $this->config = $config ?: $this->loadConfig();
        $this->logger = Logger::getInstance();
        $this->initializeAlipayConfig();
    }
    
    private function loadConfig(): array
    {
        $configPath = __DIR__ . '/../../config/alipay.php';
        if (!file_exists($configPath)) {
            throw new \Exception('Alipay configuration file not found');
        }
        
        return require $configPath;
    }
    
    private function initializeAlipayConfig(): void
    {
        $this->alipayConfig = new AlipayConfig();
        $this->alipayConfig->setServerUrl($this->config['server_url']);
        $this->alipayConfig->setAppId($this->config['app_id']);
        $this->alipayConfig->setPrivateKey($this->config['private_key']);
        $this->alipayConfig->setAlipayPublicKey($this->config['alipay_public_key']);
        
        $this->alipayConfigUtil = new AlipayConfigUtil($this->alipayConfig);
        
        $this->logger->info('Alipay client initialized', [
            'app_id' => $this->config['app_id'],
            'server_url' => $this->config['server_url']
        ]);
    }
    
    public function getAlipayConfig(): AlipayConfig
    {
        return $this->alipayConfig;
    }
    
    public function getAlipayConfigUtil(): AlipayConfigUtil
    {
        return $this->alipayConfigUtil;
    }
    
    public function getConfig(): array
    {
        return $this->config;
    }
    
    public function updateConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
        $this->initializeAlipayConfig();
    }
    
    public function validateConfig(): bool
    {
        $required = ['app_id', 'private_key', 'alipay_public_key', 'server_url'];
        
        foreach ($required as $key) {
            if (empty($this->config[$key])) {
                $this->logger->error("Missing required config: {$key}");
                return false;
            }
        }
        
        return true;
    }
} 