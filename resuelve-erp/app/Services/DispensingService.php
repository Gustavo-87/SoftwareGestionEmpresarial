<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DispensingService
{
    public function dispense(Product $product, int $quantity): void
    {
        DB::transaction(function () use ($product, $quantity) {
            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'La cantidad debe ser positiva.',
                ]);
            }

            $product = Product::whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lots = $product->lots()
                ->whereDate('expiration_date', '>=', today())
                ->where('quantity', '>', 0)
                ->orderBy('expiration_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lots->sum('quantity') < $quantity ||
                $product->stock < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Inventario disponible insuficiente.',
                ]);
            }

            $remaining = $quantity;

            foreach ($lots as $lot) {
                $take = min($remaining, $lot->quantity);
                $lot->decrement('quantity', $take);
                $remaining -= $take;

                if ($remaining === 0) {
                    break;
                }
            }

            $product->decrement('stock', $quantity);
        });
    }
}
