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
use Illuminate\Support\Facades\Cache;

final class DiscountStore implements Adjuster
{
	use HasWriteableTitleAndDescription;
	use IsLockable;
	use IsNotIncluded;

	private mixed $cart;
	private mixed $item;
	private $value;

	private float $single_amount;
	private float $amount;
	private float $price_before;

	public function __construct(mixed $cart, mixed $item, float $value)
	{
		$this->cart = $cart;
		$this->item = $item;
		$this->value = $this->resolveStoreDiscountValue($value);

		$this->price_before = $item->getAdjustedPrice();

		$prices = $item->product->calculatePrice('perc', $this->value, $this->price_before);
		
		$maxMnsrmStoreDiscount = $this->getMaxMnsrmStoreDiscount();
		if ($this->item->product->isMNSRM() && null !== $maxMnsrmStoreDiscount && $prices->discount > $maxMnsrmStoreDiscount) {
			$prices->discount = min($maxMnsrmStoreDiscount, $prices->price_init);
			$prices->price = Utilities::RoundPrice($prices->price_init - $prices->discount);
		}

		$this->single_amount = $prices->discount;
		$this->amount = $prices->discount * $item->quantity();

		debug("Product [" . $this->item->product->name . "] --- Base price [" . $this->item->getAdjustedPrice() . "] --- Applying STORE DISCOUNT --- Value per unit [$this->single_amount] --- Final applied value [$this->amount]");

		$this->setTitle('frontoffice.store-discount');
	}
	
	private function resolveStoreDiscountValue(float $fallbackValue): float
	{
		if ($this->item->product->isMSRM()) {
			return 0;
		}

		if ($this->item->product->isMNSRM()) {
			return (float) Cache::get('settings.store_discount_mnsrm', $fallbackValue);
		}

		return $fallbackValue;
	}

	private function getMaxMnsrmStoreDiscount(): ?float
	{
		$value = Cache::get('settings.max_store_discount_mnsrm');

		if (null === $value || '' === $value) {
			return null;
		}

		return (float) $value;
	}

	public static function reproduceFromAdjustment(Adjustment $adjustment): Adjuster
	{
		$data = $adjustment->getData();

		$cart = Cart::model();
		$item = CartItem::find($adjustment->adjustable_id);

		return new self($cart, $item, $data['value'] ?? 0);
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
			'type' 				=> AdjustmentTypeProxy::STORE_DISCOUNT(),
			'adjustable' 		=> $adjustable,
			'adjuster' 			=> $this,
			'origin' 			=> 'store',
			'title' 			=> $this->getTitle(),
			'description' 		=> $this->getDescription(),
			'data' 				=> [
				'value'			=> $this->value,
				'single_amount' => Utilities::RoundPrice($this->single_amount),
				'amount' 		=> Utilities::RoundPrice($this->amount),
				'price_before' 	=> Utilities::RoundPrice($this->price_before),
			],
			'amount' 			=> $this->calculateAmount($adjustable),
			'is_locked' 		=> $this->isLocked(),
			'is_included' 		=> $this->isIncluded(),
		];
	}
}
