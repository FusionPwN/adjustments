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

	public function __construct(PaymentMethod $payment)
	{
		$this->payment = $payment;

		$this->setTitle('backoffice.adjustment.payment_fee');
	}

	public static function reproduceFromAdjustment(Adjustment $adjustment): Adjuster
	{
		$payment = PaymentMethod::find($adjustment->origin ?? 0);

		return new self($payment);
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
		$fee = (float) $this->payment->fee;

		if ($this->payment->fee_type === 'percentage') {
			return round(($adjustable->total() * $fee) / 100, 2);
		}

		return $fee;
	}

	public function getModelAttributes(Adjustable $adjustable): array
	{
		$amount = $this->calculateAmount($adjustable);

		return [
			'type'              => AdjustmentTypeProxy::PAYMENT_FEE(),
			'adjustable' 		=> $adjustable,
			'adjuster'          => $this,
			'origin'            => $this->payment->id,
			'title'             => $this->getTitle(),
			'description'       => $this->getDescription(),
			'data'              => [
				'fee'      => $this->payment->fee,
				'fee_type' => $this->payment->fee_type,
			],
			'amount'            => $amount,
			'is_locked'         => $this->isLocked(),
			'is_included'       => $this->isIncluded(),
		];
	}
}
