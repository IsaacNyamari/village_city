<?php

namespace App\Support;

use App\Models\Product;

/**
 * Session-backed shopping cart shared by the public catalogue, the marketplace and "reorder".
 * It survives logging in, so a guest can fill a cart first and check out after signing in.
 */
class Cart
{
    public const KEY = 'marketplace.cart';

    /** @return array<int, float> product id => quantity */
    public static function all(): array
    {
        return collect(session(self::KEY, []))->map(fn ($q) => (float) $q)->filter(fn ($q) => $q > 0)->all();
    }

    public static function count(): int
    {
        return count(self::all());
    }

    public static function put(array $cart): void
    {
        session([self::KEY => array_filter($cart, fn ($q) => (float) $q > 0)]);
    }

    /** Add a product: starts at its minimum order quantity, then +1 per click. Returns the new quantity. */
    public static function add(Product $product, ?float $quantity = null): float
    {
        $cart = self::all();
        $new = $quantity ?? (isset($cart[$product->id]) ? $cart[$product->id] + 1 : max(1.0, (float) $product->min_order_qty));
        $cart[$product->id] = round($new, 2);
        self::put($cart);

        return $cart[$product->id];
    }

    public static function remove(int $productId): void
    {
        $cart = self::all();
        unset($cart[$productId]);
        self::put($cart);
    }

    public static function clear(): void
    {
        session()->forget(self::KEY);
    }
}
