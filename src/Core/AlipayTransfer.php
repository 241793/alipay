<?php

namespace AliMPay\Core;

use AliMPay\Utils\Logger;

class AlipayTransfer
{
    private $config;
    private $logger;
    
    public function __construct(array $config = [])
    {
        $this->config = $config ?: $this->loadConfig();
        $this->logger = Logger::getInstance();
    }
    
    private function loadConfig(): array
    {
        $configPath = __DIR__ . '/../../config/alipay.php';
        return file_exists($configPath) ? require $configPath : [];
    }
    
    /**
     * Generate transfer link with anti-risk control structure
     * 
     * @param float $amount Transfer amount
     * @param string $memo Transfer memo (order number)
     * @param string $userId Alipay user ID
     * @return string Transfer link
     */
    public function generateTransferLink(float $amount, string $memo, string $userId = null): string
    {
        $userId = $userId ?: $this->config['transfer_user_id'] ?? '';
        
        if (empty($userId)) {
            throw new \InvalidArgumentException('Transfer user ID is required');
        }
        
        // Check if anti-risk URL is enabled
        $antiRiskEnabled = $this->config['payment']['anti_risk_url']['enabled'] ?? true;
        
        if (!$antiRiskEnabled) {
            return $this->generateSimpleTransferLink($amount, $memo, $userId);
        }
        
        // Get configuration parameters
        $outerAppId = $this->config['payment']['anti_risk_url']['outer_app_id'] ?? '20000218';
        $innerAppId = $this->config['payment']['anti_risk_url']['inner_app_id'] ?? '20000116';
        $mdeductUrl = $this->config['payment']['anti_risk_url']['base_urls']['mdeduct_landing'] ?? 'https://render.alipay.com/p/c/mdeduct-landing';
        $renderUrl = $this->config['payment']['anti_risk_url']['base_urls']['render_scheme'] ?? 'https://render.alipay.com/p/s/i';
        
        // Step 1: Build the innermost transfer URL
        $innerParams = [
            'appId' => $innerAppId,
            'actionType' => 'toAccount',
            'goBack' => 'NO',
            'amount' => number_format($amount, 2, '.', ''),
            'userId' => $userId,
            'memo' => $memo
        ];
        
        $innerQueryString = http_build_query($innerParams);
        $innerUrl = "alipays://platformapi/startapp?{$innerQueryString}";
        
        // Step 2: Build the fourth layer
        $fourthLayerUrl = "{$renderUrl}?scheme=" . urlencode($innerUrl);
        
        // Step 3: Build the third layer
        $thirdLayerParams = [
            'appId' => $outerAppId,
            'url' => $fourthLayerUrl
        ];
        
        $thirdLayerQueryString = http_build_query($thirdLayerParams);
        $thirdLayerUrl = "alipays://platformapi/startapp?{$thirdLayerQueryString}";
        
        // Step 4: Build the second layer
        $secondLayerUrl = "{$renderUrl}?scheme=" . urlencode($thirdLayerUrl);
        
        // Step 5: Build the final outer layer
        $finalUrl = "{$mdeductUrl}?scheme=" . urlencode($secondLayerUrl);
        
        $this->logger->info('Generated anti-risk transfer link', [
            'amount' => $amount,
            'memo' => $memo,
            'userId' => $userId,
            'outer_app_id' => $outerAppId,
            'inner_app_id' => $innerAppId,
            'inner_url' => $innerUrl,
            'final_url' => $finalUrl
        ]);
        
        return $finalUrl;
    }
    
    /**
     * Generate simple transfer link (original method, kept for compatibility)
     * 
     * @param float $amount Transfer amount
     * @param string $memo Transfer memo (order number)
     * @param string $userId Alipay user ID
     * @return string Transfer link
     */
    public function generateSimpleTransferLink(float $amount, string $memo, string $userId = null): string
    {
        $userId = $userId ?: $this->config['transfer_user_id'] ?? '';
        
        if (empty($userId)) {
            throw new \InvalidArgumentException('Transfer user ID is required');
        }
        
        $params = [
            'appId' => '09999988',
            'actionType' => 'toAccount',
            'goBack' => 'NO',
            'amount' => number_format($amount, 2, '.', ''),
            'userId' => $userId,
            'memo' => $memo
        ];
        
        $queryString = http_build_query($params);
        $transferUrl = "alipays://platformapi/startapp?{$queryString}";
        
        $this->logger->info('Generated simple transfer link', [
            'amount' => $amount,
            'memo' => $memo,
            'userId' => $userId,
            'url' => $transferUrl
        ]);
        
        return $transferUrl;
    }
    
    /**
     * Generate order number
     * 
     * @return string Order number
     */
    public function generateOrderNo(): string
    {
        // Generate random string with letters and numbers to avoid risk control
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $length = 12;
        $randomString = '';
        
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[mt_rand(0, strlen($characters) - 1)];
        }
        
        return $randomString;
    }
    
    /**
     * Validate transfer parameters
     * 
     * @param float $amount
     * @param string $memo
     * @return bool
     */
    public function validateTransferParams(float $amount, string $memo): bool
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Transfer amount must be greater than 0');
        }
        
        if (empty($memo)) {
            throw new \InvalidArgumentException('Transfer memo is required');
        }
        
        if (strlen($memo) > 100) {
            throw new \InvalidArgumentException('Transfer memo too long (max 100 characters)');
        }
        
        return true;
    }
    
    /**
     * URL encode the transfer link for QR code generation
     * 
     * @param string $transferUrl
     * @return string
     */
    public function urlEncodeTransferLink(string $transferUrl): string
    {
        return urlencode($transferUrl);
    }

    /**
     * Create a payment order and return the transfer link.
     * This method acts as a wrapper around generateTransferLink.
     *
     * @param string $orderNo The merchant's order number.
     * @param float $amount The payment amount.
     * @param string $productName The name of the product, used as a memo.
     * @return string The generated transfer link for payment.
     */
    public function createOrder(string $orderNo, float $amount, string $productName): string
    {
        // The memo for the transfer is a combination of the product name and order number
        // to ensure it's informative and unique.
        $memo = $orderNo;
        
        // Validate the parameters before generating the link.
        $this->validateTransferParams($amount, $memo);
        
        // Generate the potentially anti-risk transfer link.
        return $this->generateTransferLink($amount, $memo);
    }

    /**
     * Parse and validate anti-risk URL structure
     * 
     * @param string $url Generated URL
     * @return array URL structure analysis
     */
    public function parseAntiRiskUrl(string $url): array
    {
        $analysis = [
            'valid' => false,
            'layers' => [],
            'final_params' => []
        ];
        
        try {
            // Get configuration parameters
            $outerAppId = $this->config['payment']['anti_risk_url']['outer_app_id'] ?? '20000218';
            $mdeductUrl = $this->config['payment']['anti_risk_url']['base_urls']['mdeduct_landing'] ?? 'https://render.alipay.com/p/c/mdeduct-landing';
            $renderUrl = $this->config['payment']['anti_risk_url']['base_urls']['render_scheme'] ?? 'https://render.alipay.com/p/s/i';
            
            // Layer 1: Check outer layer
            $layer1Pattern = $mdeductUrl . '?scheme=';
            if (strpos($url, $layer1Pattern) === 0) {
                $analysis['layers']['layer1'] = $layer1Pattern;
                
                // Extract layer 2
                $schemeParam = substr($url, strlen($layer1Pattern));
                $layer2 = urldecode($schemeParam);
                
                $layer2Pattern = $renderUrl . '?scheme=';
                if (strpos($layer2, $layer2Pattern) === 0) {
                    $analysis['layers']['layer2'] = $layer2Pattern;
                    
                    // Extract layer 3
                    $schemeParam2 = substr($layer2, strlen($layer2Pattern));
                    $layer3 = urldecode($schemeParam2);
                    
                    $layer3Pattern = "alipays://platformapi/startapp?appId={$outerAppId}&url=";
                    if (strpos($layer3, $layer3Pattern) === 0) {
                        $analysis['layers']['layer3'] = $layer3Pattern;
                        
                        // Extract layer 4
                        $urlParam = substr($layer3, strlen($layer3Pattern));
                        $layer4 = urldecode($urlParam);
                        
                        $layer4Pattern = $renderUrl . '?scheme=';
                        if (strpos($layer4, $layer4Pattern) === 0) {
                            $analysis['layers']['layer4'] = $layer4Pattern;
                            
                            // Extract final layer
                            $schemeParam3 = substr($layer4, strlen($layer4Pattern));
                            $finalLayer = urldecode($schemeParam3);
                            
                            if (strpos($finalLayer, 'alipays://platformapi/startapp?') === 0) {
                                $analysis['layers']['layer5'] = 'alipays://platformapi/startapp?';
                                
                                // Parse final parameters
                                $queryString = substr($finalLayer, strlen('alipays://platformapi/startapp?'));
                                parse_str($queryString, $analysis['final_params']);
                                
                                $analysis['valid'] = true;
                            }
                        }
                    }
                }
            }
            
            $this->logger->info('Anti-risk URL analysis completed', [
                'valid' => $analysis['valid'],
                'layers_count' => count($analysis['layers']),
                'final_params' => $analysis['final_params']
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to parse anti-risk URL', [
                'error' => $e->getMessage(),
                'url' => $url
            ]);
        }
        
        return $analysis;
    }
}   