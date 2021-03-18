<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2020 Amasty (https://www.amasty.com)
 * @package Amasty_RecurringCashOnDelivery
 */


namespace Altapay\RecurringPayments\Model\Config;

use Amasty\RecurringPayments\Api\Config\ValidatorInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class ConfigurationValidator implements ValidatorInterface
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @inheritDoc
     */
    public function enumerateConfigurationIssues(): \Generator
    {
        if (!$this->scopeConfig->isSetFlag('payment/terminal5/active')) {
            yield __('Credit Card payment method is not enabled');
        }
    }
}
