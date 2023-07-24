<?php

declare(strict_types=1);

namespace Vanilo\Adjustments\Adjusters;

use Vanilo\Adjustments\Contracts\Adjustable;
use Vanilo\Adjustments\Contracts\Adjuster;
use Vanilo\Adjustments\Contracts\Adjustment;
use Vanilo\Adjustments\Models\AdjustmentProxy;
use Vanilo\Adjustments\Models\AdjustmentTypeProxy;
use Vanilo\Adjustments\Support\HasWriteableTitleAndDescription;
use Vanilo\Adjustments\Support\IsAShippingAdjusment;
use Vanilo\Adjustments\Support\IsLockable;
use Vanilo\Adjustments\Support\IsNotIncluded;

final class FeePackagingBag implements Adjuster
{
    use HasWriteableTitleAndDescription;
    use IsLockable;
    use IsNotIncluded;
	use IsAShippingAdjusment;

    private float $amount;

    public function __construct(float $amount)
    {
        $this->amount = $amount;
    }

    public static function reproduceFromAdjustment(Adjustment $adjustment): Adjuster
    {
        $data = $adjustment->getData();

        return new self(floatval($data['amount'] ?? 0));
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
            'type' 				=> AdjustmentTypeProxy::FEE_PACKAGING_BAG(),
			'adjustable_type' 	=> $adjustable->getMorphClass(),
			'adjustable_id' 	=> $adjustable->id,
            'adjuster' 			=> self::class,
            'origin' 			=> null,
            'title' 			=> $this->getTitle(),
            'description' 		=> $this->getDescription(),
            'data' 				=> ['amount' => $this->amount],
            'amount' 			=> $this->calculateAmount($adjustable),
            'is_locked' 		=> $this->isLocked(),
            'is_included' 		=> $this->isIncluded(),
        ];
    }
}
