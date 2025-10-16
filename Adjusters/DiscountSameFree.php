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

final class DiscountSameFree implements Adjuster
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
	private float $free_quantity;

	public function __construct(mixed $cart, mixed $item, Discount $discount)
	{
		$this->cart = $cart;
		$this->item = $item;
		$this->product = $item->product;
		$this->discount = $discount;

		$discount_items = collect($cart->applyableDiscounts[$discount->id]['cart_items']);
		$nr_products = $item->quantity();

		$this->free_quantity = $nr_products;

		$this->single_amount = $item->getAdjustedPrice();
		$this->amount = 0;

		$this->setTitle($this->discount->name ?? null);
	}

	public static function reproduceFromAdjustment(Adjustment $adjustment): Adjuster
	{
		$data = $adjustment->getData();

		$cart = Cart::model();
		$item = CartItem::find($adjustment->adjustable_id);
		$discount = Discount::find($adjustment->getOrigin());

		return new self($cart, $item, $discount);
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
			'type' 				=> AdjustmentTypeProxy::OFERTA_PROD_IGUAL(),
			'adjustable' 		=> $adjustable,
			'adjuster' 			=> $this,
			'origin' 			=> $this->discount->id,
			'title' 			=> $this->getTitle(),
			'description' 		=> $this->getDescription(),
			'data' 				=> [
				'single_amount' => Utilities::RoundPrice($this->single_amount),
				'amount' 		=> Utilities::RoundPrice($this->amount),
				'quantity'		=> $this->free_quantity,
				'sku'			=> $this->product->sku
			],
			'amount' 			=> $this->calculateAmount($adjustable),
			'is_locked' 		=> $this->isLocked(),
			'is_included' 		=> $this->isIncluded(),
		];
	}
}
