<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * Coupons and promotions for clients.
 */

namespace Box\Mod\Coupon\Api;

class Client extends \Api_Abstract
{
    /**
     * Get coupon details by code.
     *
     * @param array $data
     *
     * @return array
     */
    public function get($data)
    {
        $required = [
            'code' => 'Coupon code is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->findOne('Promo', 'code = :code', [':code' => $data['code']]);
        if (!$model) {
            throw new \FOSSBilling\Exception('Coupon not found');
        }

        // Check if coupon is valid for current client
        $client = $this->getIdentity();
        if (!$this->getService()->isValid($model, ['client' => $client])) {
            throw new \FOSSBilling\Exception('Coupon is not valid');
        }

        return $this->getService()->toApiArray($model, false, $client);
    }

    /**
     * Check if coupon is valid.
     *
     * @param array $data
     *
     * @return bool
     */
    public function is_valid($data)
    {
        $required = [
            'code' => 'Coupon code is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->findOne('Promo', 'code = :code', [':code' => $data['code']]);
        if (!$model) {
            return false;
        }

        $client = $this->getIdentity();
        $orderData = [
            'client' => $client,
            'products' => $data['products'] ?? [],
            'periods' => $data['periods'] ?? [],
            'order_total' => $data['order_total'] ?? 0,
        ];

        return $this->getService()->isAdvancedCouponValid($model, $orderData);
    }

    /**
     * Get client's gift cards.
     *
     * @return array
     */
    public function get_client_gift_cards()
    {
        $client = $this->getIdentity();
        $giftCards = $this->di['db']->find('GiftCard', 'client_id = :client_id AND balance > 0', [':client_id' => $client->id]);

        $result = [];
        foreach ($giftCards as $giftCard) {
            $result[] = $this->di['db']->toArray($giftCard);
        }

        return $result;
    }

    /**
     * Get client's loyalty points balance.
     *
     * @return int
     */
    public function get_client_loyalty_points()
    {
        $client = $this->getIdentity();
        $loyaltyService = $this->di['mod_service']('Client', 'Loyalty');
        
        return $loyaltyService->getClientLoyaltyPoints($client);
    }

    /**
     * Get available coupons for client.
     *
     * @return array
     */
    public function get_available_coupons()
    {
        $client = $this->getIdentity();
        
        // Get all active coupons
        $coupons = $this->di['db']->find('Promo', 'active = 1');
        
        $available = [];
        foreach ($coupons as $coupon) {
            if ($this->getService()->isValid($coupon, ['client' => $client])) {
                $available[] = $this->di['db']->toArray($coupon);
            }
        }

        return $available;
    }

    /**
     * Apply coupon to cart.
     *
     * @param array $data
     *
     * @return array
     */
    public function apply_to_cart($data)
    {
        $required = [
            'code' => 'Coupon code is required',
            'cart' => 'Cart data is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->findOne('Promo', 'code = :code', [':code' => $data['code']]);
        if (!$model) {
            throw new \FOSSBilling\Exception('Coupon not found');
        }

        $client = $this->getIdentity();
        if (!$this->getService()->isAdvancedCouponValid($model, ['client' => $client, 'order_total' => $data['cart']['total'] ?? 0])) {
            throw new \FOSSBilling\Exception('Coupon is not valid');
        }

        return $this->getService()->applyCoupon($model, $data['cart']);
    }
}