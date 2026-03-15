<?php

namespace AliMPay\Core;

use Alipay\OpenAPISDK\Api\AlipayDataBillAccountlogApi;
use Alipay\OpenAPISDK\ApiException;
use GuzzleHttp\Client;
use AliMPay\Utils\Logger;

class BillQuery
{
    private $alipayClient;
    private $logger;
    private $apiInstance;
    
    public function __construct(AlipayClient $alipayClient = null)
    {
        // Set Beijing timezone
        date_default_timezone_set('Asia/Shanghai');
        
        $this->alipayClient = $alipayClient ?: new AlipayClient();
        $this->logger = Logger::getInstance();
        $this->initializeApiInstance();
    }
    
    private function initializeApiInstance(): void
    {
        $this->apiInstance = new AlipayDataBillAccountlogApi(new Client());
        $this->apiInstance->setAlipayConfigUtil($this->alipayClient->getAlipayConfigUtil());
    }
    
    /**
     * Query account logs by date range
     * 
     * @param string $startTime Start time in format 'Y-m-d H:i:s'
     * @param string $endTime End time in format 'Y-m-d H:i:s'
     * @param int $pageNo Page number, default 1
     * @param int $pageSize Page size, default 2000
     * @return array Query result
     */
    public function queryBills(
        string $startTime,
        string $endTime,
        string $type = null,
        int $pageNo = 1,
        int $pageSize = 2000
    ): array {
        try {
            // Validate configuration
            if (!$this->alipayClient->validateConfig()) {
                throw new \Exception('Invalid Alipay configuration');
            }
            
            // Validate parameters
            $this->validateQueryParams($startTime, $endTime, $pageNo, $pageSize);
            
            $this->logger->info('Querying account logs', [
                'start_time' => $startTime,
                'end_time' => $endTime,
                'page_no' => $pageNo,
                'page_size' => $pageSize
            ]);
            
            // Execute API call - Only pass startTime and endTime, other parameters are null
            $result = $this->apiInstance->query(
                $startTime,     // startTime - required
                $endTime,       // endTime - required
                null,           // alipayOrderNo - optional
                null,           // merchantOrderNo - optional
                (string)$pageNo,    // pageNo - optional
                (string)$pageSize,  // pageSize - optional
                null,           // transCode - optional
                null,           // agreementNo - optional
                null,           // agreementProductCode - optional
                null,           // billUserId - optional
                null            // openId - optional
            );
            
            $this->logger->info('Account log query successful', [
                'result_type' => get_class($result)
            ]);
            
            return $this->formatResult($result);
            
        } catch (ApiException $e) {
            $this->logger->error('API Exception occurred', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'response_body' => $e->getResponseBody(),
                'response_headers' => $e->getResponseHeaders()
            ]);
            
            throw new \Exception('查询失败: ' . $e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            $this->logger->error('Exception occurred', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Query account logs for today
     */
    public function queryTodayBills(): array
    {
        // Set Beijing timezone
        date_default_timezone_set('Asia/Shanghai');
        
        $today = date('Y-m-d');
        $startTime = $today . ' 00:00:00';
        $endTime = $today . ' 23:59:59';
        
        return $this->queryBills($startTime, $endTime);
    }
    
    /**
     * Query account logs for yesterday
     */
    public function queryYesterdayBills(): array
    {
        // Set Beijing timezone
        date_default_timezone_set('Asia/Shanghai');
        
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $startTime = $yesterday . ' 00:00:00';
        $endTime = $yesterday . ' 23:59:59';
        
        return $this->queryBills($startTime, $endTime);
    }
    
    /**
     * Query account logs for a specific date
     */
    public function queryBillsByDate(string $date): array
    {
        // Set Beijing timezone
        date_default_timezone_set('Asia/Shanghai');
        
        $startTime = $date . ' 00:00:00';
        $endTime = $date . ' 23:59:59';
        
        return $this->queryBills($startTime, $endTime);
    }
    
    private function validateQueryParams(
        string $startTime,
        string $endTime,
        int $pageNo,
        int $pageSize
    ): void {
        // Validate date format
        if (!$this->isValidDateTime($startTime) || !$this->isValidDateTime($endTime)) {
            throw new \InvalidArgumentException('Invalid date format, expected Y-m-d H:i:s');
        }
        
        // Validate page parameters
        if ($pageNo < 1) {
            throw new \InvalidArgumentException('Page number must be greater than 0');
        }
        
        if ($pageSize < 1 || $pageSize > 2000) {
            throw new \InvalidArgumentException('Page size must be between 1 and 2000');
        }
    }
    
    private function isValidDateTime(string $dateTime): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d H:i:s', $dateTime);
        return $d && $d->format('Y-m-d H:i:s') === $dateTime;
    }
    
    private function formatResult($result): array
    {
        if (is_object($result)) {
            // Convert object to array
            $result = json_decode(json_encode($result), true);
        }
        
        // Set Beijing timezone for timestamp
        date_default_timezone_set('Asia/Shanghai');
        
        return [
            'success' => true,
            'data' => $result,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
} 