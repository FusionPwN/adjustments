<?php

declare(strict_types=1);

namespace Vanilo\Adjustments\Adjusters;

use App\Classes\Utilities;
use App\Models\Admin\Discount;
use Vanilo\Cart\Models\Cart;
use Vanilo\Adjustments\Contracts\Adjustable;
use Vanilo\Adjustments\Contracts\Adjuster;
use Vanilo\Adjustments\Contracts\Adjustment;
use Vanilo\Adjustments\Models\AdjustmentProxy;
use Vanilo\Adjustments\Models\AdjustmentTypeProxy;
use Vanilo\Adjustments\Support\HasWriteableTitleAndDescription;
use Vanilo\Adjustments\Support\IsLockable;
use Vanilo\Adjustments\Support\IsNotIncluded;

final class DiscountScalablePercNum implements Adjuster
{
	use HasWriteableTitleAndDescription;
	use IsLockable;
	use IsNotIncluded;

	private mixed $cart;
	private mixed $item;
	private Discount $discount;
	private int $level;
	private int $levels;
	private int $quantity;

	private float $single_amount;
	private float $amount;
	private float $discount_value;
	private float $price_before;

	public function __construct(mixed $cart, mixed $item, Discount $discount, int $level, int $quantity)
	{
		$this->cart = $cart;
		$this->item = $item;
		$this->discount = $discount;
		$this->level = $level;
		$this->levels = count($discount->properties->levels);
		$this->quantity = $quantity;

		$this->discount_value = (float) $discount->properties->levels[$this->level];

		$this->price_before =  $this->item->getAdjustedPrice();

		$prices = $this->item->product->calculatePrice($discount->type == '%' ? 'perc' : 'num', $this->discount_value, $this->price_before);

		$this->single_amount = $prices->discount;
		$this->amount = $prices->discount * $item->quantity();

		debug("Product [" . $this->item->product->name . "] --- Base price [$this->price_before] --- Applying discount [$discount->name] VALUE [$this->discount_value] - LEVEL [$this->level + 1] OF [$this->levels] --- Value per unit [$this->single_amount] --- Final applied value [$this->amount]");

		$this->setTitle($this->discount->name ?? null);
	}

	public static function reproduceFromAdjustment(Adjustment $adjustment): Adjuster
	{
		$data = $adjustment->getData();

		$cart = Cart::model();
		#$item = CartItem::find($adjustment->adjustable_id);
		$discount = Discount::find($adjustment->getOrigin());

		return new self($cart, $discount, null, null);
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
			'type' 				=> AdjustmentTypeProxy::OFERTA_PERCENTAGEM(),
			'adjustable_type' 	=> $adjustable->getMorphClass(),
			'adjustable_id' 	=> $adjustable->id,
			'adjuster' 			=> self::class,
			'origin' 			=> $this->discount->id,
			'title' 			=> $this->getTitle(),
			'description' 		=> $this->getDescription(),
			'data' 				=> [
				'item_id'			=> $this->item->id,
				'single_amount' 	=> Utilities::RoundPrice($this->single_amount),
				'amount' 			=> Utilities::RoundPrice($this->amount),
				'level'				=> $this->level,
				'levels'			=> $this->levels,
				'discount_value' 	=> $this->discount_value,
				'price_before' 		=> Utilities::RoundPrice($this->price_before),
				'quantity'			=> $this->quantity,
			],
			'amount' 			=> $this->calculateAmount($adjustable),
			'is_locked' 		=> $this->isLocked(),
			'is_included' 		=> $this->isIncluded(),
		];
	}
}