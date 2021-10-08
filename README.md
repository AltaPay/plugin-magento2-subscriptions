# AltaPay for Magento 2 Recurring Payments

AltaPay has made it much easier for you as merchant/developer to receive secure subscription/recurring payments in your Magento 2 web shop.

[![Latest Stable Version](http://poser.pugx.org/altapay/magento2-subscriptions/v)](https://packagist.org/packages/altapay/magento2-subscriptions)
[![License](http://poser.pugx.org/altapay/magento2-subscriptions/license)](https://packagist.org/packages/altapay/magento2-subscriptions)

## Compatibility
- Magento 2.3 and above

## Dependencies

### Download amasty packages

- Go to https://amasty.com/subscriptions-recurring-payments-for-magento-2.html
- Purchase the plugin, or login to your account if you already purchased a copy
- Go to https://amasty.com/extupdates/account/downloads/
- Click on Download
![Download](docs/download-amasty.png)
- `mkdir -p vendor`
- `cp -r ~/Downloads/SubscriptionsRecurringPaymentsforMagento2-1/upload/app/code/Amasty vendor`

## Installation
Run the following commands in Magento 2 root folder:

    composer require altapay/magento2-subscriptions
    php bin/magento setup:upgrade
    php bin/magento setup:di:compile
    php bin/magento setup:static-content:deploy


## Changelog

See [Changelog](CHANGELOG.md) for all the release notes.

## License

Distributed under the MIT License. See [LICENSE](LICENSE) for more information.

## Documentation

For more details please see [AltaPay docs](https://documentation.altapay.com/)

## Contact
Feel free to contact our support team (support@altapay.com) if you need any assistance.