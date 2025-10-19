<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Coupon;

use FOSSBilling\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    /**
     * Get coupon by ID.
     *
     * @param int $id - coupon ID
     *
     * @return \Model_Promo
     */
    public function getCouponById($id)
    {
        return $this->di['db']->getExistingModelById('Promo', $id, 'Coupon not found');
    }

    /**
     * Get paginated list of coupons.
     *
     * @param array $data
     *
     * @return array
     */
    public function getSearchQuery($data)
    {
        $sql = '
            SELECT *
            FROM promo
            WHERE 1 ';

        $search = $data['search'] ?? null;
        $id = $data['id'] ?? null;
        $status = $data['status'] ?? null;

        $params = [];
        if ($search) {
            $sql .= ' AND code LIKE :search ';
            $params['search'] = "%$search%";
        }

        if ($id) {
            $sql .= ' AND id = :id ';
            $params['id'] = $id;
        }

        if ($status) {
            $sql .= ' AND active = :status ';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY id DESC ';

        return [$sql, $params];
    }

    /**
     * Create new coupon.
     *
     * @param array $data
     *
     * @return int - new coupon ID
     */
    public function createCoupon($data)
    {
        $systemService = $this->di['mod_service']('system');
        $systemService->checkLimits('Model_Promo', 2);

        $model = $this->di['db']->dispense('Promo');
        $model->code = $data['code'];
        $model->type = $data['type'] ?? 'percentage';
        $model->value = $data['value'] ?? 0;
        $model->maxuses = $data['maxuses'] ?? 0;
        $model->freesetup = $data['freesetup'] ?? 0;
        $model->once_per_client = $data['once_per_client'] ?? 0;
        $model->recurring = $data['recurring'] ?? 0;
        $model->active = $data['active'] ?? 0;
        $model->description = $data['description'] ?? null;
        $model->start_at = !empty($data['start_at']) ? date('Y-m-d H:i:s', strtotime($data['start_at'])) : null;
        $model->end_at = !empty($data['end_at']) ? date('Y-m-d H:i:s', strtotime($data['end_at'])) : null;
        $model->products = !empty($data['products']) ? json_encode($data['products']) : null;
        $model->periods = !empty($data['periods']) ? json_encode($data['periods']) : null;
        $model->client_groups = !empty($data['client_groups']) ? json_encode($data['client_groups']) : null;
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $newId = $this->di['db']->store($model);

        $this->di['logger']->info('Created new coupon %s', $model->code);

        return $newId;
    }

    /**
     * Update coupon.
     *
     * @param \Model_Promo $model
     * @param array        $data
     *
     * @return bool
     */
    public function updateCoupon($model, $data)
    {
        $model->code = $data['code'] ?? $model->code;
        $model->type = $data['type'] ?? $model->type;
        $model->value = $data['value'] ?? $model->value;
        $model->maxuses = $data['maxuses'] ?? $model->maxuses;
        $model->freesetup = $data['freesetup'] ?? $model->freesetup;
        $model->once_per_client = $data['once_per_client'] ?? $model->once_per_client;
        $model->recurring = $data['recurring'] ?? $model->recurring;
        $model->active = $data['active'] ?? $model->active;
        $model->description = $data['description'] ?? $model->description;
        $model->start_at = !empty($data['start_at']) ? date('Y-m-d H:i:s', strtotime($data['start_at'])) : $model->start_at;
        $model->end_at = !empty($data['end_at']) ? date('Y-m-d H:i:s', strtotime($data['end_at'])) : $model->end_at;
        $model->products = !empty($data['products']) ? json_encode($data['products']) : $model->products;
        $model->periods = !empty($data['periods']) ? json_encode($data['periods']) : $model->periods;
        $model->client_groups = !empty($data['client_groups']) ? json_encode($data['client_groups']) : $model->client_groups;
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['logger']->info('Updated coupon %s', $model->code);

        return true;
    }

    /**
     * Delete coupon.
     *
     * @param \Model_Promo $model
     *
     * @return bool
     */
    public function deleteCoupon($model)
    {
        $id = $model->id;
        $this->di['db']->trash($model);
        $this->di['logger']->info('Deleted coupon #%s', $id);

        return true;
    }

    /**
     * Check if coupon is valid.
     *
     * @param \Model_Promo $model
     * @param array        $data
     *
     * @return bool
     */
    public function isValid($model, $data = [])
    {
        // Check if coupon is active
        if (!$model->active) {
            return false;
        }

        // Check if coupon has started
        if ($model->start_at && strtotime($model->start_at) > time()) {
            return false;
        }

        // Check if coupon has expired
        if ($model->end_at && strtotime($model->end_at) < time()) {
            return false;
        }

        // Check max uses
        if ($model->maxuses > 0 && $model->used >= $model->maxuses) {
            return false;
        }

        // Check client-specific restrictions
        $client = $data['client'] ?? null;
        if ($client && $model->once_per_client) {
            // Check if client has already used this coupon
            $usedCoupons = $this->di['db']->find('ClientOrder', 'promo_id = ? AND client_id = ?', [$model->id, $client->id]);
            if (!empty($usedCoupons)) {
                return false;
            }
        }

        // Check product restrictions
        $products = json_decode($model->products ?? '[]', true);
        if (!empty($products) && isset($data['products'])) {
            $cartProducts = $data['products'];
            $validProducts = false;
            foreach ($cartProducts as $cartProduct) {
                if (in_array($cartProduct['id'], $products)) {
                    $validProducts = true;
                    break;
                }
            }
            if (!$validProducts) {
                return false;
            }
        }

        // Check period restrictions
        $periods = json_decode($model->periods ?? '[]', true);
        if (!empty($periods) && isset($data['periods'])) {
            $cartPeriods = $data['periods'];
            $validPeriods = false;
            foreach ($cartPeriods as $cartPeriod) {
                if (in_array($cartPeriod, $periods)) {
                    $validPeriods = true;
                    break;
                }
            }
            if (!$validPeriods) {
                return false;
            }
        }

        // Check client group restrictions
        $clientGroups = json_decode($model->client_groups ?? '[]', true);
        if (!empty($clientGroups) && $client) {
            if (!in_array($client->client_group_id, $clientGroups)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Apply coupon to order.
     *
     * @param \Model_Promo $model
     * @param array        $order
     *
     * @return array
     */
    public function applyCoupon($model, $order)
    {
        $discount = 0;
        $items = $order['items'] ?? [];

        foreach ($items as $item) {
            // Skip if product restrictions apply and this product isn't allowed
            $products = json_decode($model->products ?? '[]', true);
            if (!empty($products) && !in_array($item['product_id'], $products)) {
                continue;
            }

            $itemPrice = $item['price'] ?? 0;
            $itemQuantity = $item['quantity'] ?? 1;

            if ($model->type == 'percentage') {
                $discount += ($itemPrice * $itemQuantity) * ($model->value / 100);
            } elseif ($model->type == 'absolute') {
                // For absolute discount, apply to the first eligible item
                $discount += min($model->value, $itemPrice * $itemQuantity);
                break; // Only apply once
            } elseif ($model->type == 'trial') {
                // Trial coupons provide free setup
                if ($model->freesetup) {
                    $discount += $item['setup_price'] ?? 0;
                }
            }
        }

        // Increment coupon usage
        $model->used = $model->used + 1;
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return [
            'discount' => $discount,
            'type' => $model->type,
            'value' => $model->value,
            'freesetup' => $model->freesetup,
        ];
    }

    /**
     * Get coupon usage statistics.
     *
     * @param \Model_Promo $model
     *
     * @return array
     */
    public function getCouponStats($model)
    {
        $usageCount = $model->used;
        $remainingUses = $model->maxuses > 0 ? $model->maxuses - $model->used : 'Unlimited';
        $isActive = $this->isValid($model);

        return [
            'usage_count' => $usageCount,
            'remaining_uses' => $remainingUses,
            'is_active' => $isActive,
            'start_date' => $model->start_at,
            'end_date' => $model->end_at,
        ];
    }

    /**
     * Create advanced coupon with complex rules.
     *
     * @param array $data
     *
     * @return int - new coupon ID
     */
    public function createAdvancedCoupon($data)
    {
        $systemService = $this->di['mod_service']('system');
        $systemService->checkLimits('Model_Promo', 2);

        $model = $this->di['db']->dispense('Promo');
        $model->code = $data['code'];
        $model->type = $data['type'] ?? 'percentage';
        $model->value = $data['value'] ?? 0;
        $model->maxuses = $data['maxuses'] ?? 0;
        $model->freesetup = $data['freesetup'] ?? 0;
        $model->once_per_client = $data['once_per_client'] ?? 0;
        $model->recurring = $data['recurring'] ?? 0;
        $model->active = $data['active'] ?? 0;
        $model->description = $data['description'] ?? null;
        $model->start_at = !empty($data['start_at']) ? date('Y-m-d H:i:s', strtotime($data['start_at'])) : null;
        $model->end_at = !empty($data['end_at']) ? date('Y-m-d H:i:s', strtotime($data['end_at'])) : null;
        $model->products = !empty($data['products']) ? json_encode($data['products']) : null;
        $model->periods = !empty($data['periods']) ? json_encode($data['periods']) : null;
        $model->client_groups = !empty($data['client_groups']) ? json_encode($data['client_groups']) : null;
        
        // Advanced coupon fields
        $model->min_order_amount = $data['min_order_amount'] ?? 0;
        $model->max_discount_amount = $data['max_discount_amount'] ?? 0;
        $model->bundle_required = $data['bundle_required'] ?? 0;
        $model->gift_card_value = $data['gift_card_value'] ?? 0;
        $model->loyalty_points_required = $data['loyalty_points_required'] ?? 0;
        $model->valid_on_weekends = $data['valid_on_weekends'] ?? 1;
        $model->valid_on_holidays = $data['valid_on_holidays'] ?? 1;
        $model->apply_to_addons = $data['apply_to_addons'] ?? 1;
        $model->stackable = $data['stackable'] ?? 0;
        
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $newId = $this->di['db']->store($model);

        $this->di['logger']->info('Created new advanced coupon %s', $model->code);

        return $newId;
    }

    /**
     * Check if advanced coupon is valid.
     *
     * @param \Model_Promo $model
     * @param array        $data
     *
     * @return bool
     */
    public function isAdvancedCouponValid($model, $data = [])
    {
        // First check basic validity
        if (!$this->isValid($model, $data)) {
            return false;
        }

        // Check minimum order amount
        if ($model->min_order_amount > 0) {
            $orderTotal = $data['order_total'] ?? 0;
            if ($orderTotal < $model->min_order_amount) {
                return false;
            }
        }

        // Check weekend restriction
        if (!$model->valid_on_weekends) {
            $dayOfWeek = date('w');
            if ($dayOfWeek == 0 || $dayOfWeek == 6) { // Sunday or Saturday
                return false;
            }
        }

        // Check holiday restriction
        if (!$model->valid_on_holidays) {
            $today = date('Y-m-d');
            $holidays = $this->getHolidayDates();
            if (in_array($today, $holidays)) {
                return false;
            }
        }

        // Check loyalty points requirement
        if ($model->loyalty_points_required > 0) {
            $client = $data['client'] ?? null;
            if ($client) {
                $clientPoints = $this->getClientLoyaltyPoints($client);
                if ($clientPoints < $model->loyalty_points_required) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get holiday dates (simplified implementation).
     *
     * @return array
     */
    private function getHolidayDates()
    {
        // This would typically come from a holiday calendar or database
        return [
            date('Y-01-01'), // New Year's Day
            date('Y-12-25'), // Christmas Day
            // Add more holidays as needed
        ];
    }

    /**
     * Get client loyalty points (simplified implementation).
     *
     * @param \Model_Client $client
     *
     * @return int
     */
    private function getClientLoyaltyPoints($client)
    {
        // This would typically come from a loyalty points system
        // For now, we'll return a mock value
        return 100;
    }

    /**
     * Apply bundle discount.
     *
     * @param \Model_Promo $model
     * @param array        $order
     *
     * @return array
     */
    public function applyBundleDiscount($model, $order)
    {
        if (!$model->bundle_required) {
            return $this->applyCoupon($model, $order);
        }

        $items = $order['items'] ?? [];
        $itemCount = count($items);

        // Check if order qualifies for bundle discount
        if ($itemCount < 2) {
            return [
                'discount' => 0,
                'type' => $model->type,
                'value' => $model->value,
                'message' => 'Bundle discount requires at least 2 items',
            ];
        }

        // Apply bundle discount
        $result = $this->applyCoupon($model, $order);
        $result['message'] = 'Bundle discount applied';

        return $result;
    }

    /**
     * Generate gift card.
     *
     * @param \Model_Promo $model
     * @param array        $data
     *
     * @return string
     */
    public function generateGiftCard($model, $data)
    {
        if ($model->gift_card_value <= 0) {
            throw new \FOSSBilling\Exception('Invalid gift card value');
        }

        // Generate unique gift card code
        $code = 'GC-' . strtoupper(substr(md5(uniqid()), 0, 8));

        // Create gift card record
        $giftCard = $this->di['db']->dispense('GiftCard');
        $giftCard->code = $code;
        $giftCard->value = $model->gift_card_value;
        $giftCard->balance = $model->gift_card_value;
        $giftCard->promo_id = $model->id;
        $giftCard->client_id = $data['client_id'] ?? null;
        $giftCard->created_at = date('Y-m-d H:i:s');
        $giftCard->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($giftCard);

        return $code;
    }

    /**
     * Get gift card by code.
     *
     * @param string $code
     *
     * @return \Model_GiftCard
     */
    public function getGiftCardByCode($code)
    {
        return $this->di['db']->findOne('GiftCard', 'code = ?', [$code]);
    }

    /**
     * Redeem gift card.
     *
     * @param \Model_GiftCard $giftCard
     * @param float           $amount
     *
     * @return bool
     */
    public function redeemGiftCard($giftCard, $amount)
    {
        if ($giftCard->balance < $amount) {
            throw new \FOSSBilling\Exception('Insufficient gift card balance');
        }

        $giftCard->balance -= $amount;
        $giftCard->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($giftCard);

        // Log redemption
        $redemption = $this->di['db']->dispense('GiftCardRedemption');
        $redemption->gift_card_id = $giftCard->id;
        $redemption->amount = $amount;
        $redemption->created_at = date('Y-m-d H:i:s');
        $this->di['db']->store($redemption);

        return true;
    }
}