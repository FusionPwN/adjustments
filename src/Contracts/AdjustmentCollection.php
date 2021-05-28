<?php

declare(strict_types=1);

/**
 * Contains the AdjustmentCollection interface.
 *
 * @copyright   Copyright (c) 2021 Attila Fulop
 * @author      Attila Fulop
 * @license     MIT
 * @since       2021-05-28
 *
 */

namespace Vanilo\Adjustments\Contracts;

use ArrayAccess;
use Countable;

interface AdjustmentCollection extends ArrayAccess, Countable
{
    public function total(): float;

    public function isEmpty(): bool;

    public function isNotEmpty(): bool;

    public function add(Adjustment $adjustment): void;

    public function remove(Adjustment $adjustment): void;

    public function byType(AdjustmentType $type): AdjustmentCollection;
}
