<?php

namespace App\Collections;

use Illuminate\Support\Collection;

class CartCollection extends Collection
{
    /**
     * Override sum() from EnumeratesValues trait.
     */
    public function sum($callback = null)
    {
        // let the trait do its original work
        $subtotal = parent::sum($callback);

        // apply modification on the result
        $tax = $subtotal * 0.13;
        $total = $subtotal + $tax;

        return round($total, 2);
    }
}
