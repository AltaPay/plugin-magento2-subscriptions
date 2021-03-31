<?php
/**
 * Altapay Module for Magento 2.x.
 *
 * Copyright © 2021 Altapay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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
