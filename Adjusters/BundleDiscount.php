<?php

declare(strict_types=1);

namespace Vanilo\Adjustments\Adjusters;

use App\Classes\Utilities;
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
use Illuminate\Support\Collection;

final class BundleDiscount implements Adjuster
{
	use HasWriteableTitleAndDescription;
	use IsLockable;
	use IsNotIncluded;

	private mixed $cart;
	private mixed $item;
	private array $bundleConfig = [];
	private Collection $bundleItems;

	private float $single_amount;
	private float $amount;

	public function __construct(mixed $cart, mixed $item, Collection $bundleItems)
	{
		$this->cart = $cart;
		$this->item = $item;
		$this->bundleItems = $bundleItems;

		$this->single_amount = 0;
		$this->amount = 0;

		foreach ($this->bundleItems as $bundleItem) {
			$prices = $bundleItem->product->calculatePrice(
				$bundleItem->discount_type === 'percentage' ? 'perc' : 'num',
				(float) ($bundleItem->discount_value ?? 0),
				$bundleItem->product->getPriceVat()
			);

			$this->single_amount += $prices->discount;
			$this->amount += $prices->discount * $this->item->quantity();

			$this->bundleConfig[] = [
				'id' => $bundleItem->id,
				'product_id' => $bundleItem->product_id,
				'discount_type' => $bundleItem->discount_type,
				'discount_value' => $bundleItem->discount_value,
				'discount_amount' => $prices->discount,
			];
		}

		$this->setTitle('BUNDLE [' . $this->item->product->sku . ']' ?? null);
	}

	public static function reproduceFromAdjustment(Adjustment $adjustment): Adjuster
	{
		$data = $adjustment->getData();

		$cart = Cart::model();
		$item = CartItem::find($adjustment->adjustable_id);

		return new self($cart, $item);
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
			'type' 				=> AdjustmentTypeProxy::BUNDLE_DISCOUNT(),
			'adjustable' 		=> $adjustable,
			'adjuster' 			=> $this,
			'origin' 			=> $this->item->product->id,
			'title' 			=> $this->getTitle(),
			'description' 		=> $this->getDescription(),
			'data' 				=> [
				'single_amount' => Utilities::RoundPrice($this->single_amount),
				'amount' 		=> Utilities::RoundPrice($this->amount),
				'bundle_config' => $this->bundleConfig
			],
			'amount' 			=> $this->calculateAmount($adjustable),
			'is_locked' 		=> $this->isLocked(),
			'is_included' 		=> $this->isIncluded(),
		];
	}
}
