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
use SDM\Altapay\Api\Subscription\ChargeSubscription;
use SDM\Altapay\Model\SystemConfig;
use Amasty\RecurringPayments\Model\ResourceModel\Transaction\CollectionFactory;

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

    /**
     * @var CollectionFactory
     */
    private $transactionCollectionFactory;

    /**
     * @var SystemConfig
     */
    private $systemConfig;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        Emulation $emulation,
        StoreManagerInterface $storeManager,
        CompositeHandlerFactory $compositeHandlerFactory,
        HandleOrderContextFactory $handleOrderContextFactory,
        RecurringTransactionGeneratorInterface $recurringTransactionGenerator,
        CollectionFactory $transactionCollectionFactory,
        SystemConfig $systemConfig
    ) {
        $this->orderRepository = $orderRepository;
        $this->emulation = $emulation;
        $this->storeManager = $storeManager;
        $this->compositeHandlerFactory = $compositeHandlerFactory;
        $this->handleOrderContextFactory = $handleOrderContextFactory;
        $this->recurringTransactionGenerator = $recurringTransactionGenerator;
        $this->transactionCollectionFactory = $transactionCollectionFactory;
        $this->systemConfig    = $systemConfig;
    }

    /**
     * @param SubscriptionInterface $subscription
     */
    public function process(SubscriptionInterface $subscription)
    {
        $transactionId = uniqid(self::TRANSACTION_PREFIX, true);

        /** @var \Amasty\RecurringPayments\Model\ResourceModel\Transaction\Collection $transactionCollection */
        $transactionCollection = $this->transactionCollectionFactory->create();
        $order = $this->orderRepository->get($subscription->getOrderId());
        $storeCode        = $order->getStore()->getCode();
        $payment          = $order->getPayment();
        $grandTotal       = (float)$order->getGrandTotal();
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
        $select = $transactionCollection->getSelect();
        $select->where('transaction_id = ?', $transactionId);
        $getwayPaymentID = '';
        /** @var TransactionInterface $transaction */
        foreach ($transactionCollection as $transaction) {
            $parentOrder = $this->orderRepository->get($transaction->getOrderId());
            $getwayPaymentID = $parentOrder->getPayment()->getData('last_trans_id');
        }
        if($getwayPaymentID){
            // create new call for charge subscription
            $api = new ChargeSubscription($this->systemConfig->getAuth($storeCode));
            $api->setTransaction($payment->getLastTransId());
            $api->setAmount(round($grandTotal, 2));
            try {
                $logger->info(print_r("I am in Subscription Reservartion",true));
                $response = $api->call();
            } catch (ResponseHeaderException $e) {
                $this->altapayLogger->addInfoLog('Info', $e->getHeader());
                $this->altapayLogger->addCriticalLog('Response header exception', $e->getMessage());
                throw $e;
            } catch (\Exception $e) {
                $this->altapayLogger->addCriticalLog('Exception', $e->getMessage());
            }
            if (!isset($response->Result) || $response->Result != 'Success') {
                throw new \InvalidArgumentException('Could not capture subscription');
            }
        }

    }
}
