<?php
/**
 * AltaPay Recurring Payments Module for Magento 2.x.
 *
 * Copyright © 2021 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Altapay_RecurringPayments',
    __DIR__
);
