<?php
/**
* @author Amasty Team
* @copyright Copyright (c) 2022 Amasty (https://www.amasty.com)
* @package Subscriptions & Recurring Payments Cash on Delivery for Magento 2 (System)
*/

declare(strict_types=1);

namespace Altapay\RecurringPayments\Model\Processor\Transaction;

use Amasty\RecurringPayments\Api\Generators\RecurringTransactionGeneratorInterface;
use Altapay\RecurringPayments\Model\Processor\Transaction\TransactionIdGenerator;
use Amasty\RecurringPayments\Model\Config\Source\Status;
use Amasty\RecurringPayments\Model\Subscription\HandleOrder\HandleOrderContext;
use Amasty\RecurringPayments\Model\Subscription\HandleOrder\HandlerPartInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class TransactionGeneratorPart implements HandlerPartInterface
{
    /**
     * @var RecurringTransactionGeneratorInterface
     */
    private $recurringTransactionGenerator;

    /**
     * @var TransactionIdGenerator
     */
    private $transactionIdGenerator;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    public function __construct(
        RecurringTransactionGeneratorInterface $recurringTransactionGenerator,
        TransactionIdGenerator $transactionIdGenerator,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->recurringTransactionGenerator = $recurringTransactionGenerator;
        $this->transactionIdGenerator = $transactionIdGenerator;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @param HandleOrderContext $context
     * @return bool
     */
    public function handlePartial(HandleOrderContext $context): bool
    {
        $subscription = $context->getSubscription();
        $order = $this->orderRepository->get($subscription->getOrderId());
        $transactionId = $this->transactionIdGenerator->generateTransactionId();
        $context->setTransactionId($transactionId);

        $recurringTransaction = $this->recurringTransactionGenerator->generate(
            (float)$context->getQuote()->getBaseGrandTotal(),
            $order->getIncrementId(),
            $order->getOrderCurrencyCode(),
            $transactionId,
            Status::SUCCESS,
            $subscription->getSubscriptionId()
        );

        $context->setRecurringTransaction($recurringTransaction);

        return true;
    }

    /**
     * @param HandleOrderContext $context
     * @throws \InvalidArgumentException
     */
    public function validate(HandleOrderContext $context): void
    {
        if (!$context->getSubscription()) {
            throw new \InvalidArgumentException('No subscription in context');
        }

        if (!$context->getQuote()) {
            throw new \InvalidArgumentException('No quote in context');
        }
    }
}
