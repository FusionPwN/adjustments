<?php

declare(strict_types=1);

namespace Vanilo\Adjustments\Adjusters;

use App\Classes\Utilities;
use App\Models\Admin\Discount;
use App\Models\Admin\Product;
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

final class DiscountLeastExpensiveFree implements Adjuster
{
	use HasWriteableTitleAndDescription;
	use IsLockable;
	use IsNotIncluded;

	private mixed $cart;
	private mixed $item;
	private Product $product;
	private Discount $discount;

	private float $single_amount;
	private float $amount;
	private float $offer_quantity;

	public function __construct(mixed $cart, mixed $item, Discount $discount, int $offer_quantity)
	{
		$this->cart = $cart;
		$this->item = $item;
		$this->product = $item->product;
		$this->discount = $discount;
		$this->offer_quantity = $offer_quantity;

		$this->single_amount = $item->getAdjustedPrice();
		$this->amount = $this->single_amount * $this->offer_quantity;

		$this->setTitle($this->discount->name ?? null);
	}

	public static function reproduceFromAdjustment(Adjustment $adjustment): Adjuster
	{
		$data = $adjustment->getData();

		$cart = Cart::model();
		$item = CartItem::find($adjustment->adjustable_id);
		$discount = Discount::find($adjustment->getOrigin());

		return new self($cart, $item, $discount, 0);
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
			'type' 						=> AdjustmentTypeProxy::OFERTA_BARATO(),
			'adjustable_type' 			=> $adjustable->getMorphClass(),
			'adjustable_id' 			=> $adjustable->id,
			'adjuster' 					=> self::class,
			'origin' 					=> $this->discount->id,
			'title' 					=> $this->getTitle(),
			'description' 				=> $this->getDescription(),
			'data' 						=> [
				'single_amount' 		=> Utilities::RoundPrice($this->single_amount),
				'amount' 				=> Utilities::RoundPrice($this->amount),
				'quantity'				=> $this->offer_quantity,
				'sku'					=> $this->product->sku
			],
			'amount' 					=> $this->calculateAmount($adjustable),
			'is_locked' 				=> $this->isLocked(),
			'is_included' 				=> $this->isIncluded(),
		];
	}
}
