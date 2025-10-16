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
use Vanilo\Product\Models\ProductProxy;

final class CouponPerc implements Adjuster
{
	use HasWriteableTitleAndDescription;
	use IsLockable;
	use IsNotIncluded;

	private mixed $cart;
	private $item;
	private Coupon $coupon;

	private float $single_amount;
	private float $amount;

	private float $nr_possible_gifts = 0;
	private array $possible_gifts = [];
	private array $selected_gifts = [];

	public function __construct(mixed $cart, $item = null, Coupon $coupon)
	{
		$this->cart = $cart;
		$this->item = $item;
		$this->coupon = $coupon;

		$prices = $this->item->product->calculatePrice('perc', $coupon->value, $this->item->getAdjustedPrice());
		$this->single_amount = $prices->discount;
		$this->amount = $prices->discount * $item->quantity();

		if ($this->coupon->offers_products == 1 && $cart->itemsTotal() > $this->coupon->offer_product_min_purchase_value) {
			$this->nr_possible_gifts 	= 1;
			$this->possible_gifts 		= ProductProxy::withoutEvents(function () {
				return $this->coupon->gifts->pluck('id')->toArray();
			});
			$this->selected_gifts 		= session('checkout.coupon-selected_gifts', []);
		}

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

	public function getModelAttributes(Adjustable $adjustable): array
	{
		return [
			'type' 				=> AdjustmentTypeProxy::COUPON_PERC_NUM(),
			'adjustable' 		=> $adjustable,
			'adjuster' 			=> self::class,
			'origin' 			=> $this->coupon->id,
			'title' 			=> $this,
			'description' 		=> $this->getDescription(),
			'data' 				=> [
				'single_amount' => Utilities::RoundPrice($this->single_amount),
				'amount' 		=> Utilities::RoundPrice($this->amount),
				'type' 			=> 'perc',
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
