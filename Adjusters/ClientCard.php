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
use Illuminate\Support\Facades\Cache;

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

		$this->setTitle('backoffice.adjustment.client_card');
	}

	public static function reproduceFromAdjustment(Adjustment $adjustment): Adjuster
	{
		$data = $adjustment->getData();

		return new self(floatval($data['balance'] ?? 0), $data['card'], $data['cart']);
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
		$total = max((float) $this->cart->total(), 0);
		$availableBalance = max((float) $this->balance, 0);
		$onlyPayProducts = (int) Cache::get('settings.client_card.only_pay_products', 0) === 1;

		if ($onlyPayProducts) {
			$shippingAmount = 0;
			$shippingAdjustment = $this->cart->getShippingAdjustment();

			if (null !== $shippingAdjustment) {
				$shippingAmount = (float) ($shippingAdjustment->display_amount ?? $shippingAdjustment->getAmount());
			}

			$packagingAmount = 0;
			$packagingAdjustment = $this->cart->getFeePackagingBagAdjustment();

			if (null !== $packagingAdjustment) {
				$packagingAmount = (float) ($packagingAdjustment->display_amount ?? $packagingAdjustment->getAmount());
			}

			$mandatoryAmount = max($shippingAmount, 0) + max($packagingAmount, 0);
			$payableProductsAmount = max($total - $mandatoryAmount, 0);
			$appliedBalance = min($availableBalance, $payableProductsAmount);
		} else {
			$appliedBalance = min($availableBalance, $total);
		}

		return -1 * Utilities::RoundPrice($appliedBalance);
	}

	public function getModelAttributes(Adjustable $adjustable): array
	{
		return [
			'type' 				=> AdjustmentTypeProxy::CLIENT_CARD(),
			'adjustable' 		=> $adjustable,
			'adjuster' 			=> $this,
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
