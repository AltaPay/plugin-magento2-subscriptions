<?php
/**
* @author Amasty Team
* @copyright Copyright (c) 2022 Amasty (https://www.amasty.com)
* @package Subscriptions & Recurring Payments Cash on Delivery for Magento 2 (System)
*/
declare(strict_types=1);

namespace Altapay\RecurringPayments\Plugin\Payment\Checks;

use Amasty\RecurringPayments\Model\Generators\QuoteGenerator;
use Magento\OfflinePayments\Model\Cashondelivery;
use Magento\Payment\Model\Checks\TotalMinMax;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Model\Quote;

class TotalMinMaxPlugin
{

    /**
     * Disable min/max total check for cash on delivery subscription quote
     *
     * @param TotalMinMax $subject
     * @param bool $result
     * @param MethodInterface $paymentMethod
     * @param Quote $quote
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterIsApplicable(
        TotalMinMax $subject,
        bool $result,
        MethodInterface $paymentMethod,
        Quote $quote
    ): bool {
        if (($paymentMethod->getCode() === 'terminal5')
            && $quote->getData(QuoteGenerator::GENERATED_FLAG)
        ) {
            return true;
        }

        return $result;
    }
}
