<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2021 Amasty (https://www.amasty.com)
 * @package Amasty_RecurringCashOnDelivery
 */


namespace Altapay\RecurringPayments\Model\Subscription;

use Amasty\RecurringPayments\Api\Data\TransactionInterface;
use Amasty\RecurringPayments\Api\Subscription\AddressInterface;
use Amasty\RecurringPayments\Api\Subscription\AddressRepositoryInterface;
use Amasty\RecurringPayments\Api\Subscription\GridInterface;
use Amasty\RecurringPayments\Api\Subscription\RepositoryInterface;
use Amasty\RecurringPayments\Api\Subscription\SubscriptionInfoInterface;
use Amasty\RecurringPayments\Api\Subscription\SubscriptionInfoInterfaceFactory;
use Amasty\RecurringPayments\Api\Subscription\SubscriptionInterface;
use Amasty\RecurringPayments\Api\TransactionRepositoryInterface;
use Amasty\RecurringPayments\Model\Date;
use Amasty\RecurringPayments\Model\DateTime\DateTimeComparer;
use Amasty\RecurringPayments\Model\Subscription\GridSource;
use Amasty\RecurringPayments\Model\Subscription\Scheduler\DateTimeInterval;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Directory\Model\CountryFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\OfflinePayments\Model\Cashondelivery;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class Grid extends GridSource implements GridInterface
{
    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var RepositoryInterface
     */
    private $subscriptionRepository;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var SubscriptionInfoInterfaceFactory
     */
    private $subscriptionInfoFactory;

    /**
     * @var AddressRepositoryInterface
     */
    private $addressRepository;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var TransactionRepositoryInterface
     */
    private $transactionRepository;

    /**
     * @var DateTimeInterval
     */
    private $dateTimeInterval;

    /**
     * @var Date
     */
    private $date;

    /**
     * @var DateTimeComparer
     */
    private $dateTimeComparer;

    public function __construct(
        Date $date,
        PriceCurrencyInterface $priceCurrency,
        CountryFactory $countryFactory,
        UrlInterface $urlBuilder,
        RepositoryInterface $subscriptionRepository,
        ProductRepositoryInterface $productRepository,
        SubscriptionInfoInterfaceFactory $subscriptionInfoFactory,
        AddressRepositoryInterface $addressRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        OrderRepositoryInterface $orderRepository,
        TransactionRepositoryInterface $transactionRepository,
        DateTimeInterval $dateTimeInterval,
        DateTimeComparer $dateTimeComparer
    ) {
        parent::__construct($date, $priceCurrency, $countryFactory);
        $this->date = $date;
        $this->urlBuilder = $urlBuilder;
        $this->subscriptionRepository = $subscriptionRepository;
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->subscriptionInfoFactory = $subscriptionInfoFactory;
        $this->addressRepository = $addressRepository;
        $this->orderRepository = $orderRepository;
        $this->transactionRepository = $transactionRepository;
        $this->dateTimeInterval = $dateTimeInterval;
        $this->dateTimeComparer = $dateTimeComparer;
    }

    /**
     * @inheritDoc
     */
    public function process(int $customerId)
    {
        $result = [];
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(
                SubscriptionInterface::CUSTOMER_ID,
                $customerId
            )->addFilter(
                SubscriptionInterface::PAYMENT_METHOD,
                'terminal5'
            )->create();

        $subscriptions = $this->subscriptionRepository->getList($searchCriteria);
        $orders = $this->getRelatedOrders($subscriptions->getItems());
        $products = $this->getRelatedProducts($subscriptions->getItems());
        $lastTransactions = $this->getRelatedLastTransactions($subscriptions->getItems());

        /** @var SubscriptionInterface $subscription */
        foreach ($subscriptions->getItems() as $subscription) {
            /** @var OrderInterface $order */
            $order = $orders[$subscription->getOrderId()] ?? null;
            /** @var Product $product */
            $product = $products[$subscription->getProductId()] ?? null;
            /** @var SubscriptionInfoInterface $subscriptionInfo */
            $subscriptionInfo = $this->subscriptionInfoFactory->create();
            $subscriptionInfo->setSubscription($subscription);
            $subscriptionInfo->setStartDate($this->formatDate(strtotime($subscription->getStartDate())));

            if ($address = $this->findAddress($subscription)) {
                $subscriptionInfo->setAddress($address);
                $this->setStreet($address);
                $this->setCountry($address);
            }

            if ($subscription->getStatus() == SubscriptionInterface::STATUS_ACTIVE) {
                $lastPaymentDate = $subscription->getLastPaymentDate();
                if ($lastPaymentDate) {
                    $nextBillingDate = $this->dateTimeInterval->getNextBillingDate(
                        $lastPaymentDate,
                        $subscription->getFrequency(),
                        $subscription->getFrequencyUnit()
                    );
                } elseif ($subscription->getTrialDays()) {
                    $nextBillingDate = $this->dateTimeInterval->getStartDateAfterTrial(
                        $subscription->getStartDate(),
                        $subscription->getTrialDays()
                    );
                } elseif (!$this->dateTimeComparer->compareDates(
                    $subscription->getCreatedAt(),
                    $subscription->getStartDate()
                )) {
                    $nextBillingDate = $subscription->getStartDate();
                } else {
                    $nextBillingDate = $this->dateTimeInterval->getNextBillingDate(
                        $subscription->getStartDate(),
                        $subscription->getFrequency(),
                        $subscription->getFrequencyUnit()
                    );
                }

                $subscriptionEndDate = $subscription->getEndDate();
                $isNextDateExists = true;
                if ($subscriptionEndDate) {
                    $subscriptionEndDateObject = new \DateTime($subscriptionEndDate);
                    $nextBillingDateObject = new \DateTime($nextBillingDate);
                    if ($nextBillingDateObject > $subscriptionEndDateObject) {
                        $isNextDateExists = false;
                    }
                }

                if ($isNextDateExists) {
                    $subscriptionInfo->setNextBilling($this->formatDate(strtotime($nextBillingDate)));
                    $baseNextBillingAmount = (float)$subscription->getBaseGrandTotalWithDiscount();

                    if ($subscription->getRemainingDiscountCycles() !== null
                        && $subscription->getRemainingDiscountCycles() < 1
                    ) {
                        $baseNextBillingAmount = (float)$subscription->getBaseGrandTotal();
                    }

                    $subscriptionInfo->setNextBillingAmount(
                        $this->formatPrice($baseNextBillingAmount, $order->getOrderCurrencyCode())
                    );
                    $subscriptionInfo->setStatus(__('Active'));
                    $subscriptionInfo->setIsActive(true);
                } else {
                    $subscriptionInfo->setStatus(__('Canceled'));
                    $subscriptionInfo->setIsActive(false);
                }

                $this->setTrial($subscriptionInfo, $subscription);
            } else {
                $subscriptionInfo->setIsActive(false);
                $subscriptionInfo->setStatus(__('Canceled'));
            }

            if ($order) {
                $subscriptionInfo->setOrderIncrementId($order->getIncrementId());
                $subscriptionInfo->setOrderLink(
                    $this->urlBuilder->getUrl('sales/order/view', ['order_id' => $order->getId()])
                );
            }

            if ($product) {
                $subscriptionInfo->setSubscriptionName($product->getName());
            }

            $lastTransaction = $lastTransactions[$subscription->getSubscriptionId()] ?? null;
            if ($lastTransaction) {
                $subscriptionInfo->setLastBilling($this->formatDate(strtotime($subscription->getLastPaymentDate())));
                $subscriptionInfo->setLastBillingAmount(
                    $this->formatPrice(
                        (float)$lastTransaction->getBillingAmount(),
                        $lastTransaction->getBillingCurrencyCode()
                    )
                );
            }

            $result[] = $subscriptionInfo;
        }

        return $result;
    }

    /**
     * @param array $subscriptions
     * @return OrderInterface[]
     */
    private function getRelatedOrders(array $subscriptions): array
    {
        $orderIds = array_map(
            function (SubscriptionInterface $subscription) {
                return $subscription->getOrderId();
            },
            $subscriptions
        );

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('entity_id', $orderIds, 'in')
            ->create();

        $searchResult = $this->orderRepository->getList($searchCriteria);

        return $searchResult->getItems();
    }

    /**
     * @param array $subscriptions
     * @return Product[]
     */
    private function getRelatedProducts(array $subscriptions): array
    {
        $productIds = array_map(
            function (SubscriptionInterface $subscription) {
                return $subscription->getProductId();
            },
            $subscriptions
        );

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('entity_id', $productIds, 'in')
            ->create();

        $searchResult = $this->productRepository->getList($searchCriteria);

        return $searchResult->getItems();
    }

    /**
     * @param array $subscriptions
     * @return TransactionInterface[]
     */
    private function getRelatedLastTransactions(array $subscriptions)
    {
        $subscriptionIds = array_map(
            function (SubscriptionInterface $subscription) {
                return $subscription->getSubscriptionId();
            },
            $subscriptions
        );

        return $this->transactionRepository->getLastRelatedTransactions($subscriptionIds);
    }

    /**
     * @param SubscriptionInterface $subscription
     * @return AddressInterface|null
     */
    private function findAddress(SubscriptionInterface $subscription)
    {
        if ($addressId = $subscription->getAddressId()) {
            try {
                return $this->addressRepository->getById($addressId);
            } catch (NoSuchEntityException $exception) {
                return null;
            }
        }

        return null;
    }

    /**
     * @param SubscriptionInfoInterface $subscriptionInfo
     * @param SubscriptionInterface $subscription
     */
    private function setTrial(SubscriptionInfoInterface $subscriptionInfo, SubscriptionInterface $subscription)
    {
        if (!$this->dateTimeInterval->isTrialPeriodActive(
            $subscription->getStartDate(),
            $subscription->getTrialDays()
        )) {
            return;
        }

        $subscriptionInfo->setTrialStartDate($subscriptionInfo->getStartDate());
        $trialEndDate = $this->dateTimeInterval->getStartDateAfterTrial(
            $subscription->getStartDate(),
            $subscription->getTrialDays()
        );
        $endDate = $this->formatDate(strtotime($trialEndDate));
        $subscriptionInfo->setTrialEndDate($endDate);
    }
}
