<?php

/**
 * Altapay Module for Magento 2.x.
 *
 * Copyright © 2021 Altapay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Altapay\RecurringPayments\Model;

use Amasty\Base\Model\ConfigProviderAbstract;
use Magento\Framework\App\Config\ScopeConfigInterface;
use SDM\Altapay\Model\SystemConfig;

class ConfigSys extends \Amasty\RecurringPayments\Model\Config
{
    /**
     * @var SystemConfig
     */
    protected $systemConfig;
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * BeforePaymentObserver constructor.
     *
     * @param SystemConfig $systemConfig
     */
    public function __construct(SystemConfig $systemConfig, ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
        $this->systemConfig = $systemConfig;
    }

    public function getSupportedPayments(): array
    {
        $terminalConfig = [];
        foreach (SystemConfig::getTerminalCodes() as $terminalName) {
            $terminalConfig[] = $this->systemConfig->getTerminalConfigFromTerminalName(
                $terminalName,
                'terminalname'
            );
        }
        $amastyPaymentsMethod = $this->scopeConfig->getValue('amasty_recurring_payments/'.self::GENERAL_BLOCK . self::SUPPORTED_PAYMENTS);
        $amastyPaymentsMethod =  $amastyPaymentsMethod ? explode(',', $amastyPaymentsMethod) : [];
        
        return array_merge($terminalConfig, $amastyPaymentsMethod);
    }
}