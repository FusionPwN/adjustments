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
	public const STORE_DISCOUNT = 'store_discount';
	public const INTERVAL_DISCOUNT = 'interval_discount';
	public const DESCONTO_PERC_EURO = 'desconto_perc_euro';
	public const OFERTA_PERCENTAGEM = 'oferta_percentagem';
	public const OFERTA_BARATO = 'oferta_barato';
	public const OFERTA_PROD_IGUAL = 'oferta_prod_igual';
	public const OFERTA_PROD = 'oferta_prod';
	public const OFERTA_DESC_CARRINHO = 'oferta_desc_carrinho';
	public const SHIPPING = 'shipping';
	public const COUPON_PERC_NUM = 'coupon_perc_num';
	public const COUPON_FREE_SHIPPING = 'coupon_free_shipping';
	public const DIRECT_DISCOUNT = 'direct_discount';

	# para separar os produtos de oferta nas listagens do checkout e backoffice
	public const VISUAL_SEPARATORS = [
		self::OFERTA_BARATO,
		self::OFERTA_PROD_IGUAL,
		self::OFERTA_PROD
	];

	# para separar as campanhas
	public const DISCOUNTS = [
		self::DESCONTO_PERC_EURO,
		self::OFERTA_PERCENTAGEM,
		self::OFERTA_BARATO,
		self::OFERTA_PROD_IGUAL,
		self::OFERTA_PROD,
		self::OFERTA_DESC_CARRINHO
	];

	public const COUPONS = [
		self::COUPON_PERC_NUM,
		self::COUPON_FREE_SHIPPING
	];

	protected static array $labels = [];

	protected static function boot()
	{
		static::$labels = [
			self::STORE_DISCOUNT => __('backoffice.adjustment.store_discount'),
			self::INTERVAL_DISCOUNT => __('backoffice.adjustment.interval_discount'),
			self::DIRECT_DISCOUNT => __('backoffice.adjustment.direct_discount'),
			self::DESCONTO_PERC_EURO => __('backoffice.adjustment.discount_percentage_euros'),
			self::OFERTA_PERCENTAGEM => __('backoffice.adjustment.percentage_offer'),
			self::OFERTA_BARATO => __('backoffice.adjustment.offer_of_the_cheapest'),
			self::OFERTA_PROD_IGUAL => __('backoffice.adjustment.equal_product_offer'),
			self::OFERTA_PROD => __('backoffice.adjustment.product_offer'),
			self::OFERTA_DESC_CARRINHO => __('backoffice.adjustment.discount_offer_on_cart'),
			self::SHIPPING => __('backoffice.adjustment.shipping_fee'),
			self::COUPON_PERC_NUM => __('backoffice.adjustment.coupon_percentage_euros'),
			self::COUPON_FREE_SHIPPING => __('backoffice.adjustment.postage_coupon'),
		];
	}

	public static function IsVisualSeparator(AdjustmentType $type): bool
	{
		if (in_array($type->value(), self::VISUAL_SEPARATORS()->value())) {
			return true;
		}

		return false;
	}

	public static function IsCampaignDiscount(AdjustmentType $type): bool
	{
		if (in_array($type->value(), self::DISCOUNTS()->value())) {
			return true;
		}

		return false;
	}

	public static function IsCoupon(AdjustmentType $type): bool
	{
		if (in_array($type->value(), self::COUPONS()->value())) {
			return true;
		}

		return false;
	}
}
