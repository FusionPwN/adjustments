<?php

declare(strict_types=1);

namespace Vanilo\Adjustments\Adjusters;

use App\Classes\Utilities;
use App\Models\Admin\Coupon;
use Vanilo\Cart\Models\Cart;
use Vanilo\Cart\Models\CartItem;
use Vanilo\Adjustments\Contracts\Adjustable;
use Vanilo\Adjustments\Contracts\Adjuster;
use Vanilo\Adjustments\Contracts\Adjustment;
use Vanilo\Adjustments\Models\AdjustmentProxy;
use Vanilo\Adjustments\Models\AdjustmentTypeProxy;
use Vanilo\Adjustments\Support\HasWriteableTitleAndDescription;
use Vanilo\Adjustments\Support\IsLockable;
use Vanilo\Adjustments\Support\IsNotIncluded;

final class CouponNum implements Adjuster
{
	use HasWriteableTitleAndDescription;
	use IsLockable;
	use IsNotIncluded;

	private mixed $cart;
	private $item;
	private Coupon $coupon;

	private float $single_amount;
	private float $amount;

	public function __construct(mixed $cart, $item = null, Coupon $coupon)
	{
		$this->cart = $cart;
		$this->item = $item;
		$this->coupon = $coupon;

		$total = $this->cart->items->sum(function ($item) {
			return $item->getAdjustedPrice([AdjustmentTypeProxy::COUPON_PERC_NUM()]) * $item->quantity();
		});

		$value = (($this->item->getAdjustedPrice([AdjustmentTypeProxy::COUPON_PERC_NUM()]) * $this->item->quantity()) / $total) * $coupon->value;
		$prices = $this->item->product->calculatePrice('num', $value, $this->item->getAdjustedPrice([AdjustmentTypeProxy::COUPON_PERC_NUM()]) * $this->item->quantity());
		
		if($prices->discount == 0){
			$this->single_amount = $prices->price;
		} else {
			$this->single_amount = $prices->discount / $this->item->quantity();
		}
		
		$this->amount = $prices->discount;

		debug("Product [" . $this->item->product->name . "] --- Applying coupon [$coupon->code] --- Value per unit [$this->single_amount] --- Final applied value [$this->amount]");

		$this->setTitle($this->coupon->name ?? null);
	}

	public static function reproduceFromAdjustment(Adjustment $adjustment): Adjuster
	{
		$data = $adjustment->getData();

		$cart = Cart::model();
		$item = CartItem::find($adjustment->adjustable_id);
		$coupon = Coupon::find($adjustment->getOrigin());

		return new self($cart, $item, $coupon);
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
			'type' 				=> AdjustmentTypeProxy::COUPON_PERC_NUM(),
			'adjustable_type' 	=> $adjustable->getMorphClass(),
			'adjustable_id' 	=> $adjustable->id,
			'adjuster' 			=> self::class,
			'origin' 			=> $this->coupon->id,
			'title' 			=> $this->getTitle(),
			'description' 		=> $this->getDescription(),
			'data' 				=> [
				'single_amount' => $this->single_amount,
				'amount' 		=> Utilities::RoundPrice($this->amount),
				'type' 			=> 'num',
			],
			'amount' 			=> $this->calculateAmount($adjustable),
			'is_locked' 		=> $this->isLocked(),
			'is_included' 		=> $this->isIncluded(),
		];
	}
}
