<?php

declare(strict_types=1);

namespace Vanilo\Adjustments\Support;

use App\Models\Admin\CouponType;

trait ExtraData
{
    private array $extra_data = [];

    private function addExtraData(string $key, mixed $item, mixed $discount): void
    {
        if ($key === 'bundle' && $item->product->isBundleProduct()) {
            $this->extra_data['bundle_items'] = [];
            $isPercentageCoupon = $discount->type->equals(CouponType::PERCENTAGE());
            $bundleItems = [];
            $bundleTotal = 0.0;

            foreach ($item->product->bundleItems as $bundleItem) {
                $bundlePrice = $bundleItem->product->calculatePrice($bundleItem->discount_type == 'percentage' ? 'perc' : 'num', (float) $bundleItem->discount_value, $bundleItem->product->getPriceVat());
                $bundleLinePrice = $bundlePrice->price;

                $bundleItems[] = [
                    'item' => $bundleItem,
                    'price' => $bundleLinePrice,
                ];
                $bundleTotal += $bundleLinePrice;
            }

            $remainingValue = round((float) $discount->value, 2);
            $lastIndex = count($bundleItems) - 1;

            foreach ($bundleItems as $index => $bundleData) {
                $bundleItem = $bundleData['item'];
                $bundleDiscountValue = (float) $discount->value;

                if (!$isPercentageCoupon) {
                    if ($bundleTotal <= 0) {
                        $bundleDiscountValue = 0.0;
                    } elseif ($index === $lastIndex) {
                        $bundleDiscountValue = $remainingValue;
                    } else {
                        $bundleDiscountValue = round(($bundleData['price'] / $bundleTotal) * $discount->value, 2);
                        $remainingValue = round($remainingValue - $bundleDiscountValue, 2);
                    }
                }

                $bundleItemPrice = $bundleItem->product->calculatePrice($isPercentageCoupon ? 'perc' : 'num', $bundleDiscountValue, $bundleData['price']);

                $this->extra_data['bundle_items'][] = [
                    'product_id'        => $bundleItem->product_id,
                    'prices'            => $bundleItemPrice,
                    'quantity'          => $bundleItem->quantity,
                    'discount_amount'   => $bundleItemPrice->discount,
                ];
            }
        }
    }
}