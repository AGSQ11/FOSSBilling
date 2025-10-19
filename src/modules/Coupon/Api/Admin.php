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
 * Coupons and promotions management.
 */

namespace Box\Mod\Coupon\Api;

class Admin extends \Api_Abstract
{
    /**
     * Get paginated list of coupons.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_list($data)
    {
        $service = $this->getService();
        [$sql, $params] = $service->getSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getDefaultPerPage();
        $pager = $this->di['pager']->getPaginatedResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $model = $this->di['db']->getExistingModelById('Promo', $item['id'], 'Coupon not found');
            $pager['list'][$key] = $this->getService()->toApiArray($model, false, $this->getIdentity());
        }

        return $pager;
    }

    /**
     * Get coupon details.
     *
     * @param array $data
     *
     * @return array
     */
    public function get($data)
    {
        $required = [
            'id' => 'Coupon ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Promo', $data['id'], 'Coupon not found');

        return $this->getService()->toApiArray($model, true, $this->getIdentity());
    }

    /**
     * Create new coupon.
     *
     * @param array $data
     *
     * @return int - new coupon ID
     */
    public function create($data)
    {
        $required = [
            'code' => 'Coupon code is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->getService()->createCoupon($data);
    }

    /**
     * Update coupon.
     *
     * @param array $data
     *
     * @return bool
     */
    public function update($data)
    {
        $required = [
            'id' => 'Coupon ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Promo', $data['id'], 'Coupon not found');

        return $this->getService()->updateCoupon($model, $data);
    }

    /**
     * Delete coupon.
     *
     * @param array $data
     *
     * @return bool
     */
    public function delete($data)
    {
        $required = [
            'id' => 'Coupon ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Promo', $data['id'], 'Coupon not found');

        return $this->getService()->deleteCoupon($model);
    }

    /**
     * Create advanced coupon with complex rules.
     *
     * @param array $data
     *
     * @return int - new coupon ID
     */
    public function create_advanced($data)
    {
        $required = [
            'code' => 'Coupon code is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->getService()->createAdvancedCoupon($data);
    }

    /**
     * Get coupon usage statistics.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_stats($data)
    {
        $required = [
            'id' => 'Coupon ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Promo', $data['id'], 'Coupon not found');

        return $this->getService()->getCouponStats($model);
    }

    /**
     * Generate gift card from coupon.
     *
     * @param array $data
     *
     * @return string - gift card code
     */
    public function generate_gift_card($data)
    {
        $required = [
            'id' => 'Coupon ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Promo', $data['id'], 'Coupon not found');

        return $this->getService()->generateGiftCard($model, $data);
    }

    /**
     * Get gift card by code.
     *
     * @param array $data
     *
     * @return array
     */
    public function get_gift_card($data)
    {
        $required = [
            'code' => 'Gift card code is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $giftCard = $this->getService()->getGiftCardByCode($data['code']);
        if (!$giftCard) {
            throw new \FOSSBilling\Exception('Gift card not found');
        }

        return $this->di['db']->toArray($giftCard);
    }

    /**
     * Redeem gift card.
     *
     * @param array $data
     *
     * @return bool
     */
    public function redeem_gift_card($data)
    {
        $required = [
            'code' => 'Gift card code is required',
            'amount' => 'Amount to redeem is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $giftCard = $this->getService()->getGiftCardByCode($data['code']);
        if (!$giftCard) {
            throw new \FOSSBilling\Exception('Gift card not found');
        }

        return $this->getService()->redeemGiftCard($giftCard, $data['amount']);
    }

    /**
     * Get loyalty points for client.
     *
     * @param array $data
     *
     * @return int
     */
    public function get_client_loyalty_points($data)
    {
        $required = [
            'client_id' => 'Client ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $client = $this->di['db']->getExistingModelById('Client', $data['client_id'], 'Client not found');
        
        return $this->getService()->getClientLoyaltyPoints($client);
    }

    /**
     * Add loyalty points to client.
     *
     * @param array $data
     *
     * @return bool
     */
    public function add_client_loyalty_points($data)
    {
        $required = [
            'client_id' => 'Client ID is required',
            'points' => 'Points to add is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $client = $this->di['db']->getExistingModelById('Client', $data['client_id'], 'Client not found');
        
        return $this->getService()->addLoyaltyPoints($client, $data['points'], $data['description'] ?? '');
    }

    /**
     * Deduct loyalty points from client.
     *
     * @param array $data
     *
     * @return bool
     */
    public function deduct_client_loyalty_points($data)
    {
        $required = [
            'client_id' => 'Client ID is required',
            'points' => 'Points to deduct is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $client = $this->di['db']->getExistingModelById('Client', $data['client_id'], 'Client not found');
        
        return $this->getService()->deductLoyaltyPoints($client, $data['points'], $data['description'] ?? '');
    }
}