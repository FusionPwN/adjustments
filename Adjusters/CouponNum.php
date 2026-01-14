<?php

declare(strict_types=1);

namespace Vanilo\Adjustments\Adjusters;

use App\Classes\Utilities;
use App\Models\Admin\Coupon;
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
use Vanilo\Product\Models\ProductProxy;

final class CouponNum implements Adjuster
{
	use HasWriteableTitleAndDescription;
	use IsLockable;
	use IsNotIncluded;

	private mixed $cart;
	private $item;
	private Coupon $coupon;

	private float $single_amount;
	private float $amount;
	private float $nr_possible_gifts = 0;
	private array $possible_gifts = [];
	private array $selected_gifts = [];

	public function __construct(mixed $cart, $item = null, Coupon $coupon)
	{
		$this->cart = $cart; // Armazena o carrinho atual.
		$this->item = $item; // Armazena o item atual do carrinho.
		$this->coupon = $coupon; // Armazena o cupão aplicado.

		$total = $this->cart->items->sum(function ($item) {
			// Calcula o total ajustado de todos os itens no carrinho.
			return $item->getAdjustedPrice([AdjustmentTypeProxy::COUPON_PERC_NUM()]) * $item->quantity();
		});

		// Evita divisão por zero verificando se o total é igual a zero.
		if ($total == 0) {
			$value = 0; // Define o valor como zero se o total for zero.
		} else {

			// Calcula o valor proporcional do cupão para o item atual.
			$value = (($this->item->getAdjustedPrice([AdjustmentTypeProxy::COUPON_PERC_NUM()])) / $total) * $coupon->value;
			$value = round($value, 2); // Arredonda o valor calculado.
			$value = $value * $this->item->quantity(); // Multiplica pelo número de itens.
			// Ajusta o valor do último item para compensar diferenças de arredondamento.
			$remainingValue = $coupon->value - $this->cart->items->reduce(function ($carry, $cartItem) use ($item, $total, $coupon) {
				if ($cartItem->id !== $item->id) {
					// Soma os valores arredondados dos outros itens.
					$value = round((($cartItem->getAdjustedPrice([AdjustmentTypeProxy::COUPON_PERC_NUM()])) / $total) * $coupon->value, 2);
					$value = $value * $cartItem->quantity(); // Multiplica pelo número de itens.
					$carry += $value;
				}
				return $carry; // Retorna o valor acumulado.
			}, 0);

			// Se o item atual for o último no carrinho, ajusta o valor para compensar.
			if ($this->cart->items->last()->id === $this->item->id) {
				$value = $remainingValue;
			}
		}

		// Calcula os preços ajustados do produto com base no valor do cupão.
		$prices = $this->item->product->calculatePrice('num', $value, $this->item->getAdjustedPrice([AdjustmentTypeProxy::COUPON_PERC_NUM()]) * $this->item->quantity());
		// Define o valor por unidade com base no desconto ou no preço ajustado.
		if ($prices->discount == 0 && $value > 0) {
			$this->single_amount = round($prices->price, 2); // Sem desconto, usa o preço ajustado.
		} else {
			$this->single_amount = round($prices->discount / $this->item->quantity(), 2); // Com desconto, calcula o valor por unidade.
		}

		$this->amount = $prices->discount; // Define o valor total do desconto.

		if ($this->coupon->offers_products == 1 && $cart->itemsTotal() > $this->coupon->offer_product_min_purchase_value) {
			$this->nr_possible_gifts 	= 1;
			$this->possible_gifts 		= ProductProxy::withoutEvents(function () {
				return $this->coupon->gifts->pluck('id')->toArray();
			});
			$this->selected_gifts 		= session('checkout.coupon-selected_gifts', []);
		}

		// Registra informações de depuração sobre o cupão aplicado.
		#debug("Product [" . $this->item->product->name . "] --- Applying coupon [$coupon->code] --- Value per unit [$this->single_amount] --- Final applied value [$this->amount]");

		$this->setTitle($this->coupon->name ?? null); // Define o título do ajuste com o nome do cupão.
	}

	public static function reproduceFromAdjustment(Adjustment $adjustment): Adjuster
	{
		$data = $adjustment->getData();

		$cart = Cart::model();
		$item = CartItem::find($adjustment->adjustable_id);
		$coupon = Coupon::find($adjustment->getOrigin());

		return new self($cart, $item, $coupon);
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
			'type' 				=> AdjustmentTypeProxy::COUPON_PERC_NUM(),
			'adjustable' 		=> $adjustable,
			'adjuster' 			=> $this,
			'origin' 			=> $this->coupon->id,
			'title' 			=> $this->getTitle(),
			'description' 		=> $this->getDescription(),
			'data' 				=> [
				'single_amount' => Utilities::RoundPrice($this->single_amount),
				'amount' 		=> Utilities::RoundPrice($this->amount),
				'type' 			=> 'num',
				'nr_possible_gifts' => $this->nr_possible_gifts,
				'possible_gifts' 	=> $this->possible_gifts,
				'selected_gifts' 	=> $this->selected_gifts
			],
			'amount' 			=> $this->calculateAmount($adjustable),
			'is_locked' 		=> $this->isLocked(),
			'is_included' 		=> $this->isIncluded(),
		];
	}
}
