<?php

declare(strict_types=1);

/**
 * Contains the AdjustmentType class.
 *
 * @copyright   Copyright (c) 2021 Attila Fulop
 * @author      Attila Fulop
 * @license     MIT
 * @since       2021-05-27
 *
 */

namespace Vanilo\Adjustments\Models;

use Konekt\Enum\Enum;
use Vanilo\Adjustments\Contracts\AdjustmentType as AdjustmentTypeContract;

/**
 * @method static AdjustmentType PROMOTION()
 * @method static AdjustmentType SHIPPING()
 * @method static AdjustmentType TAX()
 * @method static AdjustmentType MISC()
 *
 * @method bool isPromotion()
 * @method bool isShipping()
 * @method bool isTax()
 * @method bool isMisc()
 */
class AdjustmentType extends Enum implements AdjustmentTypeContract
{
	public const STORE_DISCOUNT 		= 'store_discount';
	public const INTERVAL_DISCOUNT 		= 'interval_discount';
	public const DESCONTO_PERC_EURO 	= 'desconto_perc_euro';
	public const OFERTA_PERCENTAGEM 	= 'oferta_percentagem';
	public const OFERTA_BARATO 			= 'oferta_barato';
	public const OFERTA_PROD_IGUAL 		= 'oferta_prod_igual';
	public const OFERTA_PROD 			= 'oferta_prod';
	public const OFERTA_DESC_CARRINHO 	= 'oferta_desc_carrinho';
	public const SHIPPING 				= 'shipping';
	public const COUPON_PERC_NUM 		= 'coupon_perc_num';
	public const COUPON_FREE_SHIPPING 	= 'coupon_free_shipping';
	public const COUPON_FREE_PRODUCT 	= 'coupon_free_product';
	public const DIRECT_DISCOUNT 		= 'direct_discount';
	public const CLIENT_CARD 			= 'client_card';
	public const FEE_PACKAGING_BAG 		= 'fee_packaging_bag';
	public const PAYMENT_FEE 			= 'payment_fee';
	public const FREEBIE_OFFER 			= 'freebie_offer';

	# para separar os produtos de oferta nas listagens do checkout e backoffice
	protected static $VISUAL_SEPARATORS = [
		self::OFERTA_BARATO,
		self::OFERTA_PROD_IGUAL,
		self::OFERTA_PROD,
		self::COUPON_FREE_PRODUCT,
	];

	protected static $PROMO = [
		self::DESCONTO_PERC_EURO,
		self::OFERTA_PERCENTAGEM,
		self::OFERTA_BARATO,
		self::OFERTA_PROD_IGUAL,
		self::OFERTA_PROD,
		self::OFERTA_DESC_CARRINHO,
		self::DIRECT_DISCOUNT,
		self::STORE_DISCOUNT,
	];

	# para separar as campanhas
	protected static $DISCOUNTS = [
		self::DESCONTO_PERC_EURO,
		self::OFERTA_PERCENTAGEM,
		self::OFERTA_BARATO,
		self::OFERTA_PROD_IGUAL,
		self::OFERTA_PROD,
		self::OFERTA_DESC_CARRINHO
	];

	protected static $DISCOUNTS_TOGGLE = [
		self::DESCONTO_PERC_EURO 	=> true,
		self::OFERTA_PERCENTAGEM 	=> true,
		self::OFERTA_BARATO 		=> true,
		self::OFERTA_PROD_IGUAL 	=> true,
		self::OFERTA_PROD 			=> true,
		self::OFERTA_DESC_CARRINHO 	=> false
	];

	protected static $COUPONS = [
		self::COUPON_PERC_NUM,
		self::COUPON_FREE_SHIPPING,
		self::COUPON_FREE_PRODUCT,
		self::FREEBIE_OFFER,
	];

	protected static array $labels = [];

	protected static function boot()
	{
		static::$labels = [
			self::STORE_DISCOUNT 		=> __('backoffice.adjustment.store_discount'),
			self::INTERVAL_DISCOUNT 	=> __('backoffice.adjustment.interval_discount'),
			self::DIRECT_DISCOUNT 		=> __('backoffice.adjustment.direct_discount'),
			self::DESCONTO_PERC_EURO 	=> __('backoffice.adjustment.discount_percentage_euros'),
			self::OFERTA_PERCENTAGEM 	=> __('backoffice.adjustment.percentage_offer'),
			self::OFERTA_BARATO 		=> __('backoffice.adjustment.offer_of_the_cheapest'),
			self::OFERTA_PROD_IGUAL 	=> __('backoffice.adjustment.equal_product_offer'),
			self::OFERTA_PROD 			=> __('backoffice.adjustment.product_offer'),
			self::OFERTA_DESC_CARRINHO 	=> __('backoffice.adjustment.discount_offer_on_cart'),
			self::SHIPPING 				=> __('backoffice.adjustment.shipping_fee'),
			self::COUPON_PERC_NUM 		=> __('backoffice.adjustment.coupon_percentage_euros'),
			self::COUPON_FREE_SHIPPING 	=> __('backoffice.adjustment.postage_coupon'),
			self::COUPON_FREE_PRODUCT 	=> __('backoffice.adjustment.coupon_free_product'),
			self::CLIENT_CARD 			=> __('backoffice.adjustment.client_card'),
			self::FEE_PACKAGING_BAG 	=> __('backoffice.adjustment.fee_packaging_order_bag'),
			self::PAYMENT_FEE 			=> __('backoffice.adjustment.payment_fee'),
			self::FREEBIE_OFFER 		=> __('backoffice.adjustment.freebie_offer')
		];
	}

	public static function choices(): array
	{
		$result = [];
		$choices = parent::choices();

		foreach ($choices as $key => $value) {
			if (!isset(self::$DISCOUNTS_TOGGLE[$key]) || self::$DISCOUNTS_TOGGLE[$key]) {
				$result[$key] = $value;
			}
		}

		return $result;
	}

	public static function IsVisualSeparator(AdjustmentType $type): bool
	{
		if (in_array($type->value(), self::$VISUAL_SEPARATORS)) {
			return true;
		}

		return false;
	}

	public static function IsCampaignDiscount(AdjustmentType $type): bool
	{
		if (in_array($type->value(), self::$DISCOUNTS)) {
			return true;
		}

		return false;
	}

	public static function IsCoupon(AdjustmentType $type): bool
	{
		if (in_array($type->value(), self::$COUPONS)) {
			return true;
		}

		return false;
	}

	public static function IsPromo(AdjustmentType $type): bool
	{
		if (in_array($type->value(), self::$PROMO)) {
			return true;
		}

		return false;
	}

	public static function DiscountChoices(bool $keys_only = false): array
	{
		$choices = [];

		foreach (self::choices() as $key => $value) {
			if (in_array($key, self::$DISCOUNTS)) {
				if ($keys_only) {
					$choices[] = $key;
				} else {
					$choices[$key] = $value;
				}
			}
		}

		return $choices;
	}

	public static function CouponChoices(bool $keys_only = false): array
	{
		$choices = [];

		foreach (self::choices() as $key => $value) {
			if (in_array($key, self::$COUPONS)) {
				if ($keys_only) {
					$choices[] = $key;
				} else {
					$choices[$key] = $value;
				}
			}
		}

		return $choices;
	}
}
