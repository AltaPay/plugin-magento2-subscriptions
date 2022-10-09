<?php
/**
 * Altapay Module for Magento 2.x.
 *
 * Copyright © 2021 Altapay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Altapay\RecurringPayments\Model\Config;

use Amasty\RecurringPayments\Api\Config\ValidatorInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

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
        if (!$this->scopeConfig->isSetFlag('payment/terminal5/active', ScopeInterface::SCOPE_STORE)) {
            yield __('Credit Card payment method is not enabled');
        }
    }
}
