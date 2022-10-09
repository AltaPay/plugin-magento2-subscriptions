<?php
/**
* @author Amasty Team
* @copyright Copyright (c) 2022 Amasty (https://www.amasty.com)
* @package Subscriptions & Recurring Payments Cash on Delivery for Magento 2 (System)
*/

declare(strict_types=1);

namespace Altapay\RecurringPayments\Model\Processor\Transaction;

class TransactionIdGenerator
{
    public const TRANSACTION_PREFIX = 'trans_';

    /**
     * @return string
     */
    public function generateTransactionId(): string
    {
        return uniqid(self::TRANSACTION_PREFIX, true);
    }
}
