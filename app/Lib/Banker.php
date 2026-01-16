<?php

namespace VanguardLTE\Lib;

use VanguardLTE\FishBank;
use VanguardLTE\GameBank;

/**
 * Banker - Bank Balance Management Utility
 *
 * This class provides static methods to manage different types of bank accounts
 * for gaming operations. Banks are shop-specific and track virtual currency pools
 * used for game payouts and bonuses.
 *
 * Supported Bank Types:
 * - 'slots'  : Slot games bank balance (stored in GameBank)
 * - 'bonus'  : Bonus/promotional bank balance (stored in GameBank)
 * - 'fish'   : Fish hunting games bank balance (stored in FishBank)
 * - 'table'  : Table games bank balance (stored as 'table_bank' in GameBank)
 * - 'little' : Small games/mini-games bank balance (stored in GameBank)
 *
 * Each shop has its own isolated bank accounts to manage game economies separately.
 *
 * Usage Example:
 * ```php
 * // Get the current slots bank balance for shop 5
 * $balance = Banker::get_bank(5, 'slots');
 *
 * // Increment the fish bank by 1000
 * Banker::update_bank(5, 'fish', 1000, 'inc');
 *
 * // Get all bank balances for shop 5
 * list($slots, $bonus, $fish, $table, $little) = Banker::get_all_banks(5);
 * ```
 *
 * @package VanguardLTE\Lib
 * @author VanguardLTE
 * @version 1.0
 */
class Banker {

    /**
     * Get Bank Balance
     *
     * Retrieves the current balance of a specific bank type for a given shop.
     * If the bank type is 'fish', it queries the FishBank model. For all other
     * bank types (slots, bonus, table, little), it queries the GameBank model.
     *
     * Note: The 'table' bank type is internally stored as 'table_bank' in the
     * GameBank model, and this method handles the conversion automatically.
     *
     * @param int $shop_id The ID of the shop whose bank balance to retrieve
     * @param string $bank The bank type identifier. Valid values:
     *                     'fish', 'slots', 'bonus', 'table', 'little'
     *
     * @return float|int The current balance of the specified bank, or 0 if
     *                   the shop or bank record doesn't exist
     *
     * @example
     * ```php
     * // Get the slots bank balance for shop ID 5
     * $slotsBalance = Banker::get_bank(5, 'slots');
     *
     * // Get the fish bank balance for shop ID 10
     * $fishBalance = Banker::get_bank(10, 'fish');
     * ```
     */
    public static function get_bank($shop_id, $bank){

        if( $bank == 'fish' ){
            $fish = FishBank::where(['shop_id' => $shop_id])->first();
            if($fish){
                return $fish->fish;
            }
        } else {
            $banker = GameBank::where(['shop_id' => $shop_id])->first();
            if($banker){
                if( $bank == 'table' ){
                    $bank = 'table_bank';
                }
                return $banker->$bank;
            }
        }

        return 0;

    }

    /**
     * Get All Bank Balances
     *
     * Retrieves all bank balances for a given shop in a single query operation.
     * This method is more efficient than calling get_bank() multiple times when
     * you need access to all bank balances.
     *
     * The returned array contains balances in a fixed order: slots, bonus, fish,
     * table_bank, and little. If the shop doesn't have bank records initialized,
     * all values will be 0.
     *
     * @param int $shop_id The ID of the shop whose bank balances to retrieve
     *
     * @return array An indexed array with 5 elements in order:
     *               [0] slots bank balance
     *               [1] bonus bank balance
     *               [2] fish bank balance
     *               [3] table bank balance
     *               [4] little bank balance
     *               Returns [0, 0, 0, 0, 0] if shop records don't exist
     *
     * @example
     * ```php
     * // Get all bank balances for shop ID 5
     * $banks = Banker::get_all_banks(5);
     * echo "Slots: {$banks[0]}, Bonus: {$banks[1]}, Fish: {$banks[2]}";
     *
     * // Or use list() for cleaner code
     * list($slots, $bonus, $fish, $table, $little) = Banker::get_all_banks(5);
     * echo "Total: " . ($slots + $bonus + $fish + $table + $little);
     * ```
     */
    public static function get_all_banks($shop_id){

        $fish = FishBank::where(['shop_id' => $shop_id])->first();
        $banker = GameBank::where(['shop_id' => $shop_id])->first();

        if($fish && $banker){
            return [$banker->slots, $banker->bonus, $fish->fish, $banker->table_bank, $banker->little];
        }

        return [0,0,0,0,0];

    }

    /**
     * Update Bank Balance
     *
     * Updates a specific bank balance using one of three operation types:
     * set, increment, or decrement. This is the primary method for modifying
     * bank balances when games pay out or accept bets.
     *
     * Operation Types:
     * - 'update': Sets the bank balance to the exact value specified (replaces current value)
     * - 'inc': Increments the bank balance by the specified value (adds to current value)
     * - 'dec': Decrements the bank balance by the specified value (subtracts from current value)
     *
     * Like get_bank(), this method handles the 'fish' bank separately via the FishBank model,
     * while all other bank types use the GameBank model. The 'table' bank type is automatically
     * converted to 'table_bank' internally.
     *
     * @param int $shop_id The ID of the shop whose bank to update
     * @param string $bank The bank type identifier. Valid values:
     *                     'fish', 'slots', 'bonus', 'table', 'little'
     * @param float|int $value The value to set, increment, or decrement by
     * @param string $type The operation type. Valid values:
     *                     'update' (default) - Set to exact value
     *                     'inc' - Increment by value
     *                     'dec' - Decrement by value
     *
     * @return bool Always returns true regardless of success (legacy behavior)
     *
     * @example
     * ```php
     * // Set the slots bank to exactly 50000
     * Banker::update_bank(5, 'slots', 50000, 'update');
     *
     * // Add 1000 to the fish bank (e.g., when a player loses)
     * Banker::update_bank(5, 'fish', 1000, 'inc');
     *
     * // Subtract 500 from the bonus bank (e.g., when a player wins)
     * Banker::update_bank(5, 'bonus', 500, 'dec');
     *
     * // Using default 'update' operation (third parameter optional)
     * Banker::update_bank(5, 'little', 10000);
     * ```
     *
     * @see get_bank() for retrieving current balances
     * @see get_all_banks() for retrieving all balances at once
     */
    public static function update_bank($shop_id, $bank, $value, $type='update'){

        if( $bank == 'fish' ){
            $fish = FishBank::where('shop_id', $shop_id)->first();
            if($fish){
                if( $type == 'update' ){
                    $fish->update(['fish' => $value]);
                }
                if( $type == 'inc' ){
                    $fish->increment('fish', $value);
                }
                if( $type == 'dec' ){
                    $fish->decrement('fish', $value);
                }
            }
        } else {
            $banker = GameBank::where('shop_id', $shop_id)->first();
            if($banker){
                if( $bank == 'table' ){
                    $bank = 'table_bank';
                }
                if( $type == 'update' ){
                    $banker->update([$bank => $value]);
                }
                if( $type == 'inc' ){
                    $banker->increment($bank, $value);
                }
                if( $type == 'dec' ){
                    $banker->decrement($bank, $value);
                }
            }
        }

        return true;

    }
}