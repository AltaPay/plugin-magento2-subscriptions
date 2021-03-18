<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2020 Amasty (https://www.amasty.com)
 * @package Amasty_RecurringCashOnDelivery
 */


namespace Altapay\RecurringPayments\Model\Processor;

use Magento\Sales\Api\Data\OrderInterface;

class OrderProcessor
{
    /**
     * @var CreateSubscription
     */
    private $createSubscription;

    public function __construct(CreateSubscription $createSubscription)
    {
        $this->createSubscription = $createSubscription;
    }

    /**
     * @param OrderInterface $order
     * @param \Magento\Quote\Model\Quote\Item[] $items
     */
    public function process(OrderInterface $order, array $items)
    {
        foreach ($items as $item) {
            $this->createSubscription->execute($item, $order);
        }
    }
}
