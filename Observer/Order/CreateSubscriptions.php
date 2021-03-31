<?php
/**
 * Altapay Module for Magento 2.x.
 *
 * Copyright © 2021 Altapay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Altapay\RecurringPayments\Observer\Order;

use Altapay\RecurringPayments\Model\Processor\OrderProcessor;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;

class CreateSubscriptions implements ObserverInterface
{
    /**
     * @var OrderProcessor
     */
    private $orderProcessor;

    public function __construct(
        OrderProcessor $orderProcessor
    ) {
        $this->orderProcessor = $orderProcessor;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $order = $observer->getData('order');
        $items = $observer->getData('subscription_items');

        if ($order instanceof OrderInterface && !empty($items)) {
            $this->orderProcessor->process($order, $items);
        }
    }
}
