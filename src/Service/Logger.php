<?php

namespace Adu\CheckAndCollect\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class Logger
{
    use ConfiguredService;

    public function __construct(
        #[Autowire(service: 'monolog.logger.adu_cc')]
        private readonly LoggerInterface $logger
    )
    {
    }

    public function critical($msg, $context): void
    {
        $this->logger->critical($msg, $context);
    }

    public function log(string|\Stringable $msg, bool $error = false, array $context = []): void
    {
        if (!$this->config->activeLog() && !$error) {
            return;
        }
        $error ?
            $this->logger->error($msg, $context) :
            $this->logger->debug($msg, $context);
    }
}