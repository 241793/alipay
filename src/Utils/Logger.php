<?php

namespace AliMPay\Utils;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

class Logger
{
    private static $instance = null;
    private $logger = null;
    
    private function __construct()
    {
        $this->logger = new MonologLogger('AliMPay');
        
        // Create logs directory if it doesn't exist
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Formatter for all handlers
        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            'Y-m-d H:i:s'
        );

        // Handler for INFO level and above, goes to info.log
        $infoHandler = new StreamHandler($logDir . '/info.log', MonologLogger::INFO);
        $infoHandler->setFormatter($formatter);

        // Handler for ERROR level and above, goes to error.log
        // This will capture WARNING, ERROR, CRITICAL, ALERT, EMERGENCY
        $errorHandler = new StreamHandler($logDir . '/error.log', MonologLogger::ERROR);
        $errorHandler->setFormatter($formatter);

        // Handler for all levels (including DEBUG), goes to debug.log
        // Useful for deep debugging, but can be verbose.
        $debugHandler = new StreamHandler($logDir . '/debug.log', MonologLogger::DEBUG);
        $debugHandler->setFormatter($formatter);
        
        $this->logger->pushHandler($infoHandler);
        $this->logger->pushHandler($errorHandler);
        $this->logger->pushHandler($debugHandler);
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function log(string $message, array $context = [], string $level = 'info'): void
    {
        $this->logger->log($level, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }
    
    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }
    
    public function debug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }
    
    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }
    
    public function critical(string $message, array $context = []): void
    {
        $this->logger->critical($message, $context);
    }
} 