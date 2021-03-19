<?php
/**
 * Altapay Module for Magento 2.x.
 *
 * Copyright © 2021 Altapay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Altapay\RecurringPayments\Model\Processor;

use Amasty\RecurringPayments\Api\Generators\RecurringTransactionGeneratorInterface;
use Amasty\RecurringPayments\Api\Processors\HandleSubscriptionInterface;
use Amasty\RecurringPayments\Api\Subscription\SubscriptionInterface;
use Amasty\RecurringPayments\Model\Config\Source\Status;
use Amasty\RecurringPayments\Model\Subscription\HandleOrder\CompositeHandler;
use Amasty\RecurringPayments\Model\Subscription\HandleOrder\CompositeHandlerFactory;
use Amasty\RecurringPayments\Model\Subscription\HandleOrder\HandleOrderContext;
use Amasty\RecurringPayments\Model\Subscription\HandleOrder\HandleOrderContextFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;

class HandleSubscriptionCharge implements HandleSubscriptionInterface
{
    const TRANSACTION_PREFIX = 'trans_';

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var Emulation
     */
    private $emulation;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var CompositeHandlerFactory
     */
    private $compositeHandlerFactory;

    /**
     * @var HandleOrderContextFactory
     */
    private $handleOrderContextFactory;

    /**
     * @var RecurringTransactionGeneratorInterface
     */
    private $recurringTransactionGenerator;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        Emulation $emulation,
        StoreManagerInterface $storeManager,
        CompositeHandlerFactory $compositeHandlerFactory,
        HandleOrderContextFactory $handleOrderContextFactory,
        RecurringTransactionGeneratorInterface $recurringTransactionGenerator
    ) {
        $this->orderRepository = $orderRepository;
        $this->emulation = $emulation;
        $this->storeManager = $storeManager;
        $this->compositeHandlerFactory = $compositeHandlerFactory;
        $this->handleOrderContextFactory = $handleOrderContextFactory;
        $this->recurringTransactionGenerator = $recurringTransactionGenerator;
    }

    /**
     * @param SubscriptionInterface $subscription
     */
    public function process(SubscriptionInterface $subscription)
    {
        $transactionId = uniqid(self::TRANSACTION_PREFIX, true);

        $order = $this->orderRepository->get($subscription->getOrderId());

        $this->emulation->startEnvironmentEmulation($order->getStoreId());
        $this->storeManager->getStore()->setCurrentCurrencyCode($order->getOrderCurrencyCode());

        /** @var HandleOrderContext $handleOrderContext */
        $handleOrderContext = $this->handleOrderContextFactory->create();
        $handleOrderContext->setSubscription($subscription);
        $handleOrderContext->setTransactionId($transactionId);
        /** @var CompositeHandler $compositeHandler */
        $compositeHandler = $this->compositeHandlerFactory->create();
        $compositeHandler->handle($handleOrderContext);
        $this->recurringTransactionGenerator->generate(
            (float)$handleOrderContext->getOrder()->getBaseGrandTotal(),
            $order->getIncrementId(),
            $order->getOrderCurrencyCode(),
            $transactionId,
            Status::SUCCESS,
            $subscription->getSubscriptionId()
        );

        if ($subscription->getRemainingDiscountCycles() > 0) {
            $subscription->setRemainingDiscountCycles(
                $subscription->getRemainingDiscountCycles() - 1
            );
        }

        $this->emulation->stopEnvironmentEmulation();
    }
}
