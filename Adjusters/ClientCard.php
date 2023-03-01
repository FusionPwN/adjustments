<?php

declare(strict_types=1);

namespace Vanilo\Adjustments\Adjusters;

use App\Classes\Utilities;
use App\Models\Admin\Card;
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

final class ClientCard implements Adjuster
{
	use HasWriteableTitleAndDescription;
	use IsLockable;
	use IsNotIncluded;

	private mixed $cart;

	private float $balance;
	private Card $card;

	public function __construct(float $balance, Card $card, mixed $cart)
	{
		$this->balance = $balance;
		$this->card = $card;
		$this->cart = $cart;
	}

	public static function reproduceFromAdjustment(Adjustment $adjustment): Adjuster
	{
		$data = $adjustment->getData();

        return new self(floatval($data['balance'] ?? 0),$data['card'],$data['cart']);
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
		$retiredBalnce = $this->cart->total() - $this->balance;

		$balance = 0;

		if (0 >= $retiredBalnce) {
			$balance = $this->cart->total();
		} else {
			$balance = $this->balance;
		}

		return -1 * $balance;
	}

	private function getModelAttributes(Adjustable $adjustable): array
	{
		return [
			'type' 				=> AdjustmentTypeProxy::CLIENT_CARD(),
			'adjustable_type' 	=> $adjustable->getMorphClass(),
			'adjustable_id' 	=> $adjustable->id,
			'adjuster' 			=> self::class,
			'origin' 			=> $this->card->id,
			'title' 			=> $this->getTitle(),
			'description' 		=> $this->getDescription(),
			'data' 				=> [
				'balance' 		=> Utilities::RoundPrice($this->balance),
				'card' 			=> $this->card,
				'cart'			=> $this->cart,
			],
			'amount' 			=> $this->calculateAmount($adjustable),
			'is_locked' 		=> $this->isLocked(),
			'is_included' 		=> $this->isIncluded(),
		];
	}
}
