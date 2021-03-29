<?php
namespace Altapay\RecurringPayments\Observer;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\ObserverInterface;
use Magento\Checkout\Model\Cart as CustomerCart;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Event\Observer;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Customer\Model\Session;

class ValidateCartObserver implements ObserverInterface
{
    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var RedirectInterface
     */
    protected $redirect;

    /**
     * @var Cart
     */
    protected $cart;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var UrlInterface
     */
    protected $_url;

    /**
     * @var Http
     */
    protected $request;

    /**
     * ValidateCartObserver constructor.
     *
     * @param ManagerInterface  $messageManager
     * @param RedirectInterface $redirect
     * @param CustomerCart      $cart
     * @param CheckoutSession   $checkoutSession
     * @param UrlInterface      $url
     * @param Http              $request
     */
    public function __construct(
        ManagerInterface $messageManager,
        RedirectInterface $redirect,
        CustomerCart $cart,
        CheckoutSession $checkoutSession,
        UrlInterface $url,
        Http $request
    ) {
        $this->messageManager = $messageManager;
        $this->redirect = $redirect;
        $this->cart = $cart;
        $this->checkoutSession = $checkoutSession;
        $this->_url = $url;
        $this->request = $request;
    }

    /**
     * Validate Cart Before going to checkout
     * - event: controller_action_predispatch_checkout_index_index
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $isRecurring = false;
        $isRegular = false;
        $quote = $this->checkoutSession->getQuote();
        /** @var Item $item */
        foreach ($quote->getAllItems() as $item) {
            if ($this->isSubscription($item)) {
                $isRecurring = true;
            } else {
                $isRegular = true;
            }
            if ($isRecurring === true && $isRegular === true) {
                $this->messageManager->addErrorMessage( __('You cannot add any other product with subscription products!')  );
                if($this->request->getFullActionName() === 'checkout_index_index'){
                    $url = $this->_url->getUrl('checkout/cart/index');
                    $observer->getControllerAction()
                             ->getResponse()
                             ->setRedirect($url);
                }
            }
        }
    }
    /**
     * @param AbstractItem $item
     * @return DataObject
     */
    public function getBuyRequestObject(AbstractItem $item)
    {
        /** @var DataObject $request */
        $request = $item->getBuyRequest();
        if (!$request && $item->getQuoteItem()) {
            $request = $item->getQuoteItem()->getBuyRequest();
        }
        if (!$request) {
            $request = new DataObject();
        }

        if (is_array($request)) {
            $request = new DataObject($request);
        }

        return $request;
    }
    /**
     * @param AbstractItem $item
     * @return bool
     */
    public function isSubscription(AbstractItem $item)
    {
        $buyRequest = $this->getBuyRequestObject($item);

        return $buyRequest->getData('subscribe') === 'subscribe';
    }
}