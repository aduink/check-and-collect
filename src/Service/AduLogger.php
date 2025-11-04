<?php

namespace Adu\CheckAndCollect\Service;

use JetBrains\PhpStorm\ExpectedValues;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class AduLogger implements LoggerInterface
{
    use ConfiguredService;

    public const EMERGENCY = 7;
    public const ALERT = 6;
    public const CRITICAL = 5;
    public const ERROR = 4;
    public const WARNING = 3;
    public const NOTICE = 2;
    public const INFO = 1;
    public const DEBUG = 0;


    public function __construct(
        #[Autowire(service: 'monolog.logger.adu_cc')]
        private LoggerInterface $logger
    )
    {
    }

    public function log(#[ExpectedValues(valuesFromClass: self::class)] $level, \Stringable|string $message, array $context = []): void
    {
        if($level < self::WARNING && !$this->isActive()){
            return;
        }
        $f = match($level){
            self::DEBUG => $this->logger->debug(...),
            self::INFO => $this->logger->info(...),
            self::NOTICE => $this->logger->notice(...),
            self::WARNING => $this->logger->warning(...),
            self::ERROR => $this->logger->error(...),
            self::CRITICAL => $this->logger->critical(...),
            self::ALERT => $this->logger->alert(...),
            self::EMERGENCY => $this->logger->emergency(...),
        };
        $f($message, $context);
    }



    public function isActive(): bool
    {
        return $this->config->activeLog();
    }

    public function emergency(\Stringable|string $message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    public function alert(\Stringable|string $message, array $context = []): void
    {
        $this->log(self::ALERT, $message, $context);
    }
    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    public function error(\Stringable|string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    public function warning(\Stringable|string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    public function notice(\Stringable|string $message, array $context = []): void
    {
        $this->log(self::NOTICE, $message, $context);
    }

    public function info(\Stringable|string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }
    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }
}