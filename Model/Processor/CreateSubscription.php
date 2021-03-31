<?php
/**
 * Altapay Module for Magento 2.x.
 *
 * Copyright © 2021 Altapay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Altapay\RecurringPayments\Model\Processor;

use Amasty\RecurringPayments\Model\Subscription\EmailNotifier;
use Amasty\RecurringPayments\Api\Subscription\SubscriptionInterface;
use Amasty\RecurringPayments\Model\Config;
use Amasty\RecurringPayments\Model\SubscriptionManagement;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Sales\Api\Data\OrderInterface;

class CreateSubscription
{
    const SUBSCRIPTION_PREFIX = 'altapay_';

    /**
     * @var SubscriptionManagement
     */
    private $subscriptionManagement;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var EmailNotifier
     */
    private $emailNotifier;

    public function __construct(
        SubscriptionManagement $subscriptionManagement,
        Config $config,
        EmailNotifier $emailNotifier
    ) {
        $this->subscriptionManagement = $subscriptionManagement;
        $this->config                 = $config;
        $this->emailNotifier          = $emailNotifier;
    }

    /**
     * @param AbstractItem   $item
     * @param OrderInterface $order
     *
     * @return SubscriptionInterface
     */
    public function execute(
        AbstractItem $item,
        OrderInterface $order
    ): SubscriptionInterface {
        $subscription   = $this->subscriptionManagement->generateSubscription(
            $order,
            $item
        );
        $subscriptionId = uniqid(self::SUBSCRIPTION_PREFIX, true);
        $subscription->setSubscriptionId($subscriptionId);

        $subscription = $this->subscriptionManagement->saveSubscription(
            $subscription,
            $order
        );

        $payment    = $order->getPayment();
        $initialFee = $subscription->getData('initial_fee');
        $payment->setAdditionalInformation('initial_fee', $initialFee);
        $payment->save();

        if ($this->config->isNotifySubscriptionPurchased((int)$subscription->getStoreId())) {
            $template = $this->config->getEmailTemplateSubscriptionPurchased((int)$subscription->getStoreId());
            $this->emailNotifier->sendEmail(
                $subscription,
                $template
            );
        }

        return $subscription;
    }
}
