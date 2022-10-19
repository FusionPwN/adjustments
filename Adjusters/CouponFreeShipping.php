<?php

declare(strict_types=1);

/**
 * Contains the SimpleDiscount class.
 *
 * @copyright   Copyright (c) 2022 Attila Fulop
 * @author      Attila Fulop
 * @license     MIT
 * @since       2022-03-25
 *
 */

namespace Vanilo\Adjustments\Adjusters;

use App\Classes\Utilities;
use App\Models\Admin\Coupon;
use Vanilo\Cart\Models\Cart;
use Vanilo\Adjustments\Contracts\Adjustable;
use Vanilo\Adjustments\Contracts\Adjuster;
use Vanilo\Adjustments\Contracts\Adjustment;
use Vanilo\Adjustments\Models\AdjustmentProxy;
use Vanilo\Adjustments\Models\AdjustmentTypeProxy;
use Vanilo\Adjustments\Support\HasWriteableTitleAndDescription;
use Vanilo\Adjustments\Support\IsLockable;
use Vanilo\Adjustments\Support\IsNotIncluded;

final class CouponFreeShipping implements Adjuster
{
	use HasWriteableTitleAndDescription;
	use IsLockable;
	use IsNotIncluded;

	private Cart $cart;
	private Coupon $coupon;

	private float $shippingAmount;
	private float $amount;

	public function __construct(Cart $cart, Coupon $coupon)
	{
		$this->cart = $cart;
		$this->coupon = $coupon;

		$shippingAdjustment = $this->cart->adjustments()->byType(AdjustmentTypeProxy::SHIPPING())->first();
		if (isset($shippingAdjustment)) {
			$this->shippingAmount = $shippingAdjustment->getAmount();

			if ($this->coupon->value() == 0 || $this->shippingAmount <= $this->coupon->value()) {
				$this->amount = $this->shippingAmount;
			} else if ($this->shippingAmount > $this->coupon->value()) {
				$this->amount = $this->coupon->value();
			}
		} else {
			$this->shippingAmount = 0;
			$this->amount = 0;
		}

		$this->setTitle($this->coupon->name ?? null);
	}

	public static function reproduceFromAdjustment(Adjustment $adjustment): Adjuster
	{
		$data = $adjustment->getData();

		$cart = Cart::model();
		$coupon = Coupon::find($adjustment->getOrigin());

		return new self($cart, $coupon);
	}

	public function createAdjustment(Adjustable $adjustable): Adjustment
	{
		$adjustmentClass = AdjustmentProxy::modelClass();

		return new $adjustmentClass($this->getModelAttributes($adjustable));
	}

	public function recalculate(Adjustment $adjustment, Adjustable $adjustable): Adjustment
	{
		$adjustment->setAmount($this->calculateAmount($adjustable));

		return $adjustment;
	}

	private function calculateAmount(Adjustable $adjustable): float
	{
		return -1 * $this->amount;
	}

	private function getModelAttributes(Adjustable $adjustable): array
	{
		return [
			'type' 				=> AdjustmentTypeProxy::COUPON_FREE_SHIPPING(),
			'adjustable_type' 	=> $adjustable->getMorphClass(),
			'adjustable_id' 	=> $adjustable->id,
			'adjuster' 			=> self::class,
			'origin' 			=> $this->coupon->id,
			'title' 			=> $this->getTitle(),
			'description' 		=> $this->getDescription(),
			'data' 				=> [
				'shipping_amount' 	=> Utilities::RoundPrice($this->shippingAmount),
				'amount' 			=> Utilities::RoundPrice($this->amount)
			],
			'amount' 			=> $this->calculateAmount($adjustable),
			'is_locked' 		=> $this->isLocked(),
			'is_included' 		=> $this->isIncluded(),
		];
	}
}
