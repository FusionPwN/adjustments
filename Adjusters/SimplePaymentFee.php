<?php

declare(strict_types=1);

namespace Vanilo\Adjustments\Adjusters;

use Vanilo\Adjustments\Contracts\Adjuster;
use Vanilo\Adjustments\Support\IsLockable;
use Vanilo\Adjustments\Support\IsNotIncluded;
use Vanilo\Adjustments\Support\HasWriteableTitleAndDescription;
use Vanilo\Adjustments\Support\IsAPaymentAdjusment;
use Vanilo\Framework\Models\PaymentMethod;
use Vanilo\Adjustments\Contracts\Adjustment;
use Vanilo\Adjustments\Contracts\Adjustable;
use Vanilo\Adjustments\Models\AdjustmentProxy;
use Vanilo\Adjustments\Models\AdjustmentTypeProxy;


final class SimplePaymentFee implements Adjuster
{
    use HasWriteableTitleAndDescription;
	use IsLockable;
	use IsNotIncluded;
    use IsAPaymentAdjusment;

    private PaymentMethod $payment;
	private float $amount;

    public function __construct(PaymentMethod $payment, float $amount)
	{
		$this->payment = $payment;
		$this->amount = $amount;
	}

    public static function reproduceFromAdjustment(Adjustment $adjustment): Adjuster
	{
		$data = $adjustment->getData();

		return new self(PaymentMethod::find($adjustment->origin ?? 0), floatval($data['amount'] ?? 0));
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
		return $this->amount;
	}

    private function getModelAttributes(Adjustable $adjustable): array
	{
		return [
			'type' 				=> AdjustmentTypeProxy::PAYMENT_FEE(),
			'adjustable_type' 	=> $adjustable->getMorphClass(),
			'adjustable_id' 	=> $adjustable->id,
			'adjuster' 			=> self::class,
			'origin' 			=> $this->payment->id,
			'title' 			=> $this->getTitle(),
			'description' 		=> $this->getDescription(),
			'data' 				=> ['amount' => $this->amount],
			'amount' 			=> $this->calculateAmount($adjustable),
			'is_locked' 		=> $this->isLocked(),
			'is_included' 		=> $this->isIncluded(),
		];
	}

}