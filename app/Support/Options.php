<?php

namespace App\Support;

/**
 * Central lists of option values used across forms, filters and validation.
 */
class Options
{
    public const STATUS = ['active' => 'Active', 'inactive' => 'Inactive'];

    public const SUPPLIER_TYPES = [
        'individual' => 'Individual Supplier',
        'farmer_group' => 'Farmer Group',
        'cooperative' => 'Cooperative',
        'commercial' => 'Commercial Supplier',
    ];

    public const COOPERATIVE_TYPES = ['farmer_group' => 'Farmer Group', 'cooperative' => 'Cooperative'];

    public const CENTRE_TYPES = ['collection_centre' => 'Collection Centre', 'warehouse' => 'Warehouse / Distribution Hub'];

    public const CUSTOMER_TYPES = [
        'wholesale' => 'Wholesale Trader',
        'supermarket' => 'Supermarket',
        'restaurant' => 'Restaurant',
        'hotel' => 'Hotel',
        'school' => 'School',
        'hospital' => 'Hospital',
        'university' => 'University',
        'government' => 'Government Institution',
        'processor' => 'Food Processor',
        'household' => 'Individual Household',
    ];

    public const PAYMENT_TERMS = [
        'cash_on_delivery' => 'Cash on Delivery',
        'prepaid' => 'Prepaid',
        'net_7' => 'Net 7 days',
        'net_14' => 'Net 14 days',
        'net_30' => 'Net 30 days',
    ];

    public const PAYMENT_TERM_DAYS = ['cash_on_delivery' => 0, 'prepaid' => 0, 'net_7' => 7, 'net_14' => 14, 'net_30' => 30];

    public const PAYMENT_METHODS = ['cash' => 'Cash', 'bank_transfer' => 'Bank Transfer', 'mpesa' => 'M-Pesa', 'cheque' => 'Cheque', 'other' => 'Other'];

    public const PAYMENT_STATUS = ['unpaid' => 'Unpaid', 'partial' => 'Partially Paid', 'paid' => 'Paid'];

    public const GRADES = ['A' => 'Grade A', 'B' => 'Grade B', 'C' => 'Grade C', 'rejected' => 'Rejected'];

    public const UNITS = ['kg' => 'Kilogram (kg)', 'bag_90kg' => '90kg Bag', 'bag_50kg' => '50kg Bag', 'crate' => 'Crate', 'bunch' => 'Bunch', 'piece' => 'Piece', 'litre' => 'Litre', 'tonne' => 'Tonne'];

    public const EXPENSE_CATEGORIES = [
        'transport' => 'Transport', 'packaging' => 'Packaging', 'labour' => 'Labour', 'storage' => 'Storage', 'fuel' => 'Fuel',
        'utilities' => 'Utilities', 'administration' => 'Administration', 'marketing' => 'Marketing', 'other' => 'Other',
    ];

    public const VEHICLE_TYPES = ['pickup' => 'Pickup', 'canter' => 'Canter (3–5T)', 'lorry' => 'Lorry (7–10T)', 'trailer' => 'Trailer', 'refrigerated' => 'Refrigerated Truck', 'motorbike' => 'Motorbike', 'van' => 'Van'];

    public const VEHICLE_STATUS = ['available' => 'Available', 'on_trip' => 'On Trip', 'maintenance' => 'Under Maintenance', 'inactive' => 'Inactive'];

    public const DISPATCH_STATUS = ['scheduled' => 'Scheduled', 'dispatched' => 'Dispatched', 'in_transit' => 'In Transit', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];

    public const MOVEMENT_TYPES = [
        'receipt' => 'Stock Received', 'sale' => 'Dispatched for Order', 'return' => 'Order Return', 'transfer_in' => 'Transfer In',
        'transfer_out' => 'Transfer Out', 'adjustment' => 'Adjustment', 'damage' => 'Damaged', 'expiry' => 'Expired', 'wastage' => 'Wastage',
    ];

    public const WRITE_OFF_TYPES = ['damage' => 'Damaged produce', 'expiry' => 'Expired produce', 'wastage' => 'Wastage'];

    public const COLORS = [
        'active' => 'green', 'inactive' => 'zinc', 'pending' => 'amber', 'available' => 'green', 'on_trip' => 'blue', 'maintenance' => 'amber',
        'paid' => 'green', 'partial' => 'amber', 'unpaid' => 'red', 'void' => 'zinc', 'voided' => 'zinc', 'recorded' => 'green',
        'completed' => 'green', 'reversed' => 'red', 'scheduled' => 'zinc', 'dispatched' => 'blue', 'in_transit' => 'blue',
        'cancelled' => 'red', 'A' => 'green', 'B' => 'lime', 'C' => 'amber', 'rejected' => 'red',
        'receipt' => 'green', 'sale' => 'blue', 'return' => 'teal', 'transfer_in' => 'sky', 'transfer_out' => 'sky',
        'adjustment' => 'amber', 'damage' => 'red', 'expiry' => 'red', 'wastage' => 'orange', 'in' => 'green', 'out' => 'red',
    ];

    public static function label(string $list, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $options = constant(self::class.'::'.$list);

        return $options[$value] ?? ucwords(str_replace('_', ' ', $value));
    }

    public static function color(?string $value): string
    {
        return self::COLORS[$value] ?? 'zinc';
    }

    public static function money(float|int|string|null $amount): string
    {
        return 'KES '.number_format((float) $amount, 2);
    }

    public static function qty(float|int|string|null $qty): string
    {
        return rtrim(rtrim(number_format((float) $qty, 2), '0'), '.');
    }
}
