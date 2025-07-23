<?php
/**
 * Plugin Name: WooCommerce Handling Fee by Shipping Method 
 * Description: Adds a configurable handling fee only for Interparcel shipping method. Excludes ds_local_pickup.
 * Version: 1.5
 * Author: Paperdino (Julius Enriquez)
 */

if (!defined('ABSPATH')) exit;

// Admin Menu
add_action('admin_menu', function() {
    add_submenu_page(
        'woocommerce',
        'Handling Fee Settings',
        'Handling Fee',
        'manage_woocommerce',
        'whfsm-handling-fee',
        function() {
            if (isset($_POST['whfsm_fee_amount'])) {
                check_admin_referer('whfsm_save_fee');
                update_option('whfsm_handling_fee', floatval($_POST['whfsm_fee_amount']));
                echo '<div class="updated"><p>Handling fee updated.</p></div>';
            }
            $fee = get_option('whfsm_handling_fee', 0);
            ?>
            <div class="wrap">
                <h1>Handling Fee Settings</h1>
                <form method="post">
                    <?php wp_nonce_field('whfsm_save_fee'); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="whfsm_fee_amount">Handling Fee Amount</label></th>
                            <td><input type="number" step="0.01" min="0" name="whfsm_fee_amount" value="<?php echo esc_attr($fee); ?>" class="regular-text" /></td>
                        </tr>
                    </table>
                    <?php submit_button('Save Changes'); ?>
                </form>
            </div>
            <?php
        }
    );
});

// Handling Fee Logic
add_action('woocommerce_cart_calculate_fees', function($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;

    $fee_amount = floatval(get_option('whfsm_handling_fee', 0));
    $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');

    $apply_fee = false;
    $debug_log = [];

    if (!empty($chosen_shipping_methods)) {
        foreach (WC()->shipping()->get_packages() as $index => $package) {
            $rate_id = $chosen_shipping_methods[$index];
            if (!empty($package['rates'][$rate_id])) {
                $rate = $package['rates'][$rate_id];
                $method_id = $rate->get_method_id();
                $debug_log[] = "Package {$index} method ID: {$method_id}";

                if ($method_id === 'interparcel') {
                    $apply_fee = true;
                }
                if ($method_id === 'ds_local_pickup') {
                    $apply_fee = false;
                    break; // cancel fee if any package is ds_local_pickup
                }
            }
        }
    }

    // Log method ID evaluation
    error_log("WHFSM DEBUG: " . implode(' | ', $debug_log));
    error_log("WHFSM DEBUG: apply_fee = " . ($apply_fee ? 'true' : 'false'));

    // Remove existing fee
    foreach ($cart->get_fees() as $key => $fee) {
        if ($fee->name === 'Handling Fee') {
            unset($cart->fees_api()->fees[$key]);
        }
    }

    // Add handling fee if appropriate
    if ($apply_fee && $fee_amount > 0) {
        $cart->add_fee(__('Handling Fee', 'woocommerce'), $fee_amount);
    }
}, 20, 1);

// Force checkout update when shipping method changes
add_action('wp_footer', function() {
    if (is_checkout()) {
        ?>
        <script>
        jQuery(function($){
            $('form.checkout').on('change', 'input[name^="shipping_method"]', function(){
                $('body').trigger('update_checkout');
            });
        });
        </script>
        <?php
    }
});
?>
