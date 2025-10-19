# FOSSBilling Coupon Module Documentation

## Overview
The Coupon module provides an advanced coupon and promotion management system for FOSSBilling, allowing for complex rules, gift cards, and loyalty programs.

## Features

### Advanced Coupon Rules
- **Minimum Order Amount**: Set a minimum order total for a coupon to be valid.
- **Maximum Discount Amount**: Limit the maximum discount amount a coupon can provide.
- **Bundle Discounts**: Require multiple products in the cart for a coupon to be valid.
- **Time-Based Promotions**: Set start and end dates for coupons.
- **Usage Limits**: Limit the number of times a coupon can be used.
- **Client-Specific Coupons**: Limit coupon usage to once per client.
- **Product and Period Restrictions**: Restrict coupons to specific products or billing periods.
- **Client Group Restrictions**: Restrict coupons to specific client groups.
- **Stackable Coupons**: Allow multiple coupons to be used on a single order.

### Gift Cards
- **Gift Card Generation**: Create gift cards with specific values from coupons.
- **Balance Tracking**: Track the remaining balance on gift cards.
- **Redemption**: Redeem gift cards towards invoice payments.

### Loyalty Program
- **Points System**: Earn loyalty points for purchases and other actions.
- **Points Redemption**: Redeem loyalty points for discounts or products.
- **Tiered Rewards**: Offer different rewards for different loyalty tiers.

## Database Changes

### New Columns in `promo` Table
- `min_order_amount`
- `max_discount_amount`
- `bundle_required`
- `gift_card_value`
- `loyalty_points_required`
- `valid_on_weekends`
- `valid_on_holidays`
- `apply_to_addons`
- `stackable`

### New Tables
- `gift_card`
- `gift_card_redemption`
- `holiday`
- `loyalty_points`
- `loyalty_points_transaction`

## API Endpoints

### Admin API
- `coupon_create_advanced`
- `coupon_get_stats`
- `coupon_generate_gift_card`
- `coupon_get_gift_card`
- `coupon_redeem_gift_card`
- `coupon_get_client_loyalty_points`
- `coupon_add_client_loyalty_points`
- `coupon_deduct_client_loyalty_points`

### Client API
- `coupon_is_valid`
- `coupon_get_client_gift_cards`
- `coupon_get_client_loyalty_points`
- `coupon_get_available_coupons`
- `coupon_apply_to_cart`

## Implementation Details
- **Complex Rules**: Coupon validity is checked against a set of advanced rules.
- **Gift Cards**: Gift cards are created as separate entities with their own balances.
- **Loyalty Points**: Loyalty points are tracked per client and can be redeemed for rewards.

## Security Considerations
- All coupon and gift card codes are unique.
- Usage limits and expiration dates are strictly enforced.
- Loyalty point transactions are logged for auditing.

## Extending the Module
- Add new coupon rule types.
- Create custom loyalty point earning and redemption rules.
- Integrate with third-party gift card providers.