<?php

declare(strict_types=1);

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
use Vanilo\Product\Models\ProductProxy;

final class CouponFreeProduct implements Adjuster
{
	use HasWriteableTitleAndDescription;
	use IsLockable;
	use IsNotIncluded;

	private mixed $cart;
	private Coupon $coupon;

	private float $single_amount = 0;
	private float $amount = 0;
	private float $nr_possible_gifts = 0;
	private array $possible_gifts;
	private array $selected_gifts = [];

	public function __construct(mixed $cart, Coupon $coupon)
	{
		$this->cart = $cart;
		$this->coupon = $coupon;

		$this->nr_possible_gifts 	= $this->coupon->value;
		$this->possible_gifts 		= ProductProxy::withoutEvents(function () {
			return $this->coupon->gifts->pluck('id')->toArray();
		});
		$this->selected_gifts 		= session('checkout.coupon-selected_gifts', []);

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
			'type' 				=> AdjustmentTypeProxy::COUPON_FREE_PRODUCT(),
			'adjustable_type' 	=> $adjustable->getMorphClass(),
			'adjustable_id' 	=> $adjustable->id,
			'adjuster' 			=> self::class,
			'origin' 			=> $this->coupon->id,
			'title' 			=> $this->getTitle(),
			'description' 		=> $this->getDescription(),
			'data' 				=> [
				'single_amount' 	=> Utilities::RoundPrice($this->single_amount),
				'amount' 			=> Utilities::RoundPrice($this->amount),
				'nr_possible_gifts' => $this->nr_possible_gifts,
				'possible_gifts' 	=> $this->possible_gifts,
				'selected_gifts' 	=> $this->selected_gifts
			],
			'amount' 			=> $this->calculateAmount($adjustable),
			'is_locked' 		=> $this->isLocked(),
			'is_included' 		=> $this->isIncluded(),
		];
	}
}
