<?php
/**
 * AltaPay Recurring Payments Module for Magento 2.x.
 *
 * Copyright © 2021 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Altapay\RecurringPayments\Model\Processor;

use Altapay\RecurringPayments\Model\Processor\Transaction\TransactionGeneratorPart;
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
use Altapay\Api\Subscription\ChargeSubscription;
use SDM\Altapay\Model\SystemConfig;
use SDM\Altapay\Logger\Logger;

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
     * @var TransactionGeneratorPart
     */
    private $transactionGeneratorPart;

    /**
     * @var SystemConfig
     */
    private $systemConfig;

    /**
     * @var Logger
     */
    private $altapayLogger;

    /**
     * HandleSubscriptionCharge constructor.
     *
     * @param OrderRepositoryInterface               $orderRepository
     * @param Emulation                              $emulation
     * @param StoreManagerInterface                  $storeManager
     * @param CompositeHandlerFactory                $compositeHandlerFactory
     * @param HandleOrderContextFactory              $handleOrderContextFactory
     * @param RecurringTransactionGeneratorInterface $recurringTransactionGenerator
     * @param SystemConfig                           $systemConfig
     * @param Logger                                 $altapayLogger
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        Emulation $emulation,
        StoreManagerInterface $storeManager,
        CompositeHandlerFactory $compositeHandlerFactory,
        HandleOrderContextFactory $handleOrderContextFactory,
        TransactionGeneratorPart $transactionGeneratorPart,
        SystemConfig $systemConfig,
        Logger $altapayLogger
    ) {
        $this->orderRepository               = $orderRepository;
        $this->emulation                     = $emulation;
        $this->storeManager                  = $storeManager;
        $this->compositeHandlerFactory       = $compositeHandlerFactory;
        $this->handleOrderContextFactory     = $handleOrderContextFactory;
        $this->transactionGeneratorPart = $transactionGeneratorPart;
        $this->systemConfig                  = $systemConfig;
        $this->altapayLogger                 = $altapayLogger;
    }

    /**
     * @param SubscriptionInterface $subscription
     */
    public function process(SubscriptionInterface $subscription)
    {
        $transactionId = uniqid(self::TRANSACTION_PREFIX, true);
        $order         = $this->orderRepository->get($subscription->getOrderId());
        $storeCode     = $order->getStore()->getCode();
        $payment       = $order->getPayment();

        if ($payment->getData('last_trans_id')) {
            $this->emulation->startEnvironmentEmulation($order->getStoreId());
            $this->storeManager->getStore()->setCurrentCurrencyCode($order->getOrderCurrencyCode());

            /** @var HandleOrderContext $handleOrderContext */
            $handleOrderContext = $this->handleOrderContextFactory->create();
            $handleOrderContext->setSubscription($subscription);
            $handleOrderContext->setTransactionId($transactionId);
            /** @var CompositeHandler $compositeHandler */
            $compositeHandler = $this->compositeHandlerFactory->create();
            $compositeHandler->addPart($this->transactionGeneratorPart, 'recurringpayment_transaction', 'quote');
            $compositeHandler->handle($handleOrderContext);

            if ($subscription->getRemainingDiscountCycles() > 0) {
                $subscription->setRemainingDiscountCycles(
                    $subscription->getRemainingDiscountCycles() - 1
                );
            }
            $this->emulation->stopEnvironmentEmulation();

            // create new call for charge subscription
            $api = new ChargeSubscription($this->systemConfig->getAuth($storeCode));
            $api->setTransaction($payment->getLastTransId());
            $api->setAmount((float)$handleOrderContext->getOrder()->getBaseGrandTotal());
            try {
                $response = $api->call();
            } catch (ResponseHeaderException $e) {
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
