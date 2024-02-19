<?php

declare(strict_types=1);

namespace Vanilo\Adjustments\Adjusters;

use App\Models\Admin\ShipmentMethod;
use Vanilo\Adjustments\Contracts\Adjustable;
use Vanilo\Adjustments\Contracts\Adjuster;
use Vanilo\Adjustments\Contracts\Adjustment;
use Vanilo\Adjustments\Models\AdjustmentProxy;
use Vanilo\Adjustments\Models\AdjustmentTypeProxy;
use Vanilo\Adjustments\Support\HasWriteableTitleAndDescription;
use Vanilo\Adjustments\Support\IsAShippingAdjusment;
use Vanilo\Adjustments\Support\IsLockable;
use Vanilo\Adjustments\Support\IsNotIncluded;

final class SimpleShippingFee implements Adjuster
{
	use HasWriteableTitleAndDescription;
	use IsLockable;
	use IsNotIncluded;
	use IsAShippingAdjusment;

	private ShipmentMethod $shipping;
	private float $amount;
	private ?float $freeThreshold;

	public function __construct(ShipmentMethod $shipping, float $amount, ?float $freeThreshold = null)
	{
		$this->shipping = $shipping;
		$this->amount = $amount;
		$this->freeThreshold = $freeThreshold;
	}

	public static function reproduceFromAdjustment(Adjustment $adjustment): Adjuster
	{
		$data = $adjustment->getData();

		return new self(ShipmentMethod::find($adjustment->origin ?? 0), floatval($data['amount'] ?? 0), $data['freeThreshold'] ?? null);
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
		if(isset($adjustable->activeCoupon)){
			$subTotal = $adjustable->subTotal() - $this->amount;
		} else {
			$subTotal = $adjustable->subTotal();
		}
		
		if (null !== $this->freeThreshold && $subTotal >= $this->freeThreshold) {
			debug("Adding shipping free --- Cart total [" . $subTotal . "] --- Threshold [$this->freeThreshold] --- Final applied value [0]");
			return 0;
		}

		debug("Adding shipping free --- Cart total [" . $subTotal . "] --- Threshold [$this->freeThreshold] --- Final applied value [$this->amount]");

		return $this->amount;
	}

	private function getModelAttributes(Adjustable $adjustable): array
	{
		return [
			'type' 				=> AdjustmentTypeProxy::SHIPPING(),
			'adjustable_type' 	=> $adjustable->getMorphClass(),
			'adjustable_id' 	=> $adjustable->id,
			'adjuster' 			=> self::class,
			'origin' 			=> $this->shipping->id,
			'title' 			=> $this->getTitle(),
			'description' 		=> $this->getDescription(),
			'data' 				=> ['amount' => $this->amount, 'freeThreshold' => $this->freeThreshold],
			'amount' 			=> $this->calculateAmount($adjustable),
			'is_locked' 		=> $this->isLocked(),
			'is_included' 		=> $this->isIncluded(),
		];
	}
}
