<?php
/**
 * Plugin Name: Onedev Mode Maintenance Simple
 * Description: Un plugin léger de mode maintenance avec page de réglages : activation, texte personnalisé et logo.
 * Version: 1.1.0
 * Author: onedev.ovh
 * Author URI: https://onedev.ovh
 * Requires PHP: 8.1
 * Requires at least: 6.6
 * Tested up to: 7.1
 * Text Domain: onedev-maintenance-mode
 * Plugin URI: https://onedev.ovh
 * Domain Path: /languages/
 * License: GPL2
 */

defined( 'ABSPATH' ) || exit;

class Onedev_Maintenance_Mode {

    private $option_name = 'onedev_maintenance_settings';

    public function __construct() {
        register_activation_hook( __FILE__, array( $this, 'clear_caches' ) );
        register_deactivation_hook( __FILE__, array( $this, 'clear_caches' ) );

        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
        add_action( 'admin_notices', array( $this, 'admin_notice' ) );
        add_action( 'template_redirect', array( $this, 'render_maintenance_page' ) );

        $this->disable_feeds();
    }

    public function get_settings() {
        $defaults = array(
            'enabled'     => 1,
            'title'       => 'Nous mettons à jour le site',
            'message'     => 'Notre site est actuellement en mode maintenance pour vous offrir une meilleure expérience. Nous serons de retour dans très peu de temps.',
            'logo'        => '',
            'badge_text'  => 'En Mode Maintenance',
        );

        $saved = get_option( $this->option_name, array() );

        return wp_parse_args( $saved, $defaults );
    }

    public function register_settings() {
        register_setting(
            'onedev_maintenance_group',
            $this->option_name,
            array( $this, 'sanitize_settings' )
        );
    }

    public function sanitize_settings( array $input ) {
        return array(
            'enabled'    => ! empty( $input['enabled'] ) ? 1 : 0,
            'title'      => isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '',
            'message'    => isset( $input['message'] ) ? sanitize_textarea_field( $input['message'] ) : '',
            'logo'       => isset( $input['logo'] ) ? esc_url_raw( $input['logo'] ) : '',
            'badge_text' => isset( $input['badge_text'] ) ? sanitize_text_field( $input['badge_text'] ) : '',
        );
    }

    public function admin_menu() {
        add_options_page(
            'Onedev Maintenance',
            'Onedev Maintenance',
            'manage_options',
            'onedev-maintenance-mode',
            array( $this, 'settings_page' )
        );
    }

    public function admin_assets( string $hook ) {
        if ( 'settings_page_onedev-maintenance-mode' !== $hook ) {
            return;
        }

        wp_enqueue_media();

        wp_add_inline_script(
            'jquery-core',
            "
            jQuery(document).ready(function($){
                let mediaFrame;

                $('#onedev-upload-logo').on('click', function(e){
                    e.preventDefault();

                    if (mediaFrame) {
                        mediaFrame.open();
                        return;
                    }

                    mediaFrame = wp.media({
                        title: 'Choisir un logo',
                        button: { text: 'Utiliser ce logo' },
                        multiple: false
                    });

                    mediaFrame.on('select', function(){
                        const attachment = mediaFrame.state().get('selection').first().toJSON();
                        $('#onedev_logo').val(attachment.url);
                        $('#onedev-logo-preview').html('<img src=\"' + attachment.url + '\" style=\"max-width:180px;height:auto;border-radius:8px;\" />');
                    });

                    mediaFrame.open();
                });

                $('#onedev-remove-logo').on('click', function(e){
                    e.preventDefault();
                    $('#onedev_logo').val('');
                    $('#onedev-logo-preview').html('');
                });
            });
            "
        );
    }

    public function settings_page() {
        $settings = $this->get_settings();
        ?>
        <div class="wrap">
            <h1>Onedev Maintenance Mode</h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'onedev_maintenance_group' ); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">Activer le mode maintenance</th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[enabled]" value="1" <?php checked( 1, $settings['enabled'] ); ?>>
                                Activer / Désactiver la page de maintenance
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="onedev_title">Titre</label></th>
                        <td>
                            <input type="text" id="onedev_title" class="regular-text" name="<?php echo esc_attr( $this->option_name ); ?>[title]" value="<?php echo esc_attr( $settings['title'] ); ?>">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="onedev_message">Texte</label></th>
                        <td>
                            <textarea id="onedev_message" class="large-text" rows="5" name="<?php echo esc_attr( $this->option_name ); ?>[message]"><?php echo esc_textarea( $settings['message'] ); ?></textarea>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="onedev_badge_text">Badge texte</label></th>
                        <td>
                            <input type="text" id="onedev_badge_text" class="regular-text" name="<?php echo esc_attr( $this->option_name ); ?>[badge_text]" value="<?php echo esc_attr( $settings['badge_text'] ); ?>">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Logo</th>
                        <td>
                            <input type="hidden" id="onedev_logo" name="<?php echo esc_attr( $this->option_name ); ?>[logo]" value="<?php echo esc_url( $settings['logo'] ); ?>">
                            <p>
                                <button id="onedev-upload-logo" class="button button-secondary">Choisir un logo</button>
                                <button id="onedev-remove-logo" class="button">Supprimer le logo</button>
                            </p>
                            <div id="onedev-logo-preview" style="margin-top:10px;">
                                <?php if ( ! empty( $settings['logo'] ) ) : ?>
                                    <img src="<?php echo esc_url( $settings['logo'] ); ?>" style="max-width:180px;height:auto;border-radius:8px;" />
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                </table>

                <p class="submit" style="display: flex; gap: 15px; align-items: center;">
                    <?php submit_button( 'Enregistrer les modifications', 'primary', 'submit', false ); ?>
                    <a href="<?php echo esc_url( home_url( '?preview_onedev_maintenance=1' ) ); ?>" target="_blank" class="button button-secondary">👀 Prévisualiser la page</a>
                </p>
            </form>
        </div>
        <?php
    }

    public function clear_caches() {
        if ( has_action( 'litespeed_purge_all' ) ) {
            do_action( 'litespeed_purge_all' );
        }

        if ( function_exists( 'rocket_clean_domain' ) ) {
            rocket_clean_domain();
        }

        if ( function_exists( 'wp_cache_clear_cache' ) ) {
            wp_cache_clear_cache();
        }

        if ( function_exists( 'w3tc_pgcache_flush' ) ) {
            w3tc_pgcache_flush();
        }
    }

    public function admin_notice() {
        $settings = $this->get_settings();

        if ( current_user_can( 'manage_options' ) && ! empty( $settings['enabled'] ) ) {
            echo '<div class="notice notice-warning is-dismissible"><p><strong>⚠️ Onedev Maintenance Mode</strong> est actuellement <strong>ACTIF</strong>.</p></div>';
        }
    }

    public function render_maintenance_page() {
        $settings = $this->get_settings();
        
        // التحقق مما إذا كان المدير يطلب معاينة الصفحة
        $is_preview = isset( $_GET['preview_onedev_maintenance'] ) && current_user_can( 'manage_options' );

        if ( empty( $settings['enabled'] ) && ! $is_preview ) {
            return;
        }

        if ( current_user_can( 'manage_options' ) && ! $is_preview ) {
            return;
        }

        nocache_headers();
        header( 'HTTP/1.1 503 Service Temporarily Unavailable' );
        header( 'Status: 503 Service Temporarily Unavailable' );
        header( 'Retry-After: 3600' );

        $logo_html = '';
        if ( ! empty( $settings['logo'] ) ) {
            $logo_html = '<div style="margin-bottom:20px;"><img src="' . esc_url( $settings['logo'] ) . '" alt="Logo" style="max-width:120px;height:auto;margin:0 auto;" /></div>';
        }

        // مسار ملف الـ CSS
        $css_url = plugins_url( 'assets/css/maintenance.css', __FILE__ );

        echo '<!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Maintenance</title>
            <link rel="stylesheet" href="' . esc_url( $css_url ) . '?v=1.1.0">
        </head>
        <body>
            <div class="box">
                ' . $logo_html . '
                <h1>' . esc_html( $settings['title'] ) . '</h1>
                <p>' . nl2br( esc_html( $settings['message'] ) ) . '</p>
                <div class="badge">' . esc_html( $settings['badge_text'] ) . '</div>
            </div>
        </body>
        </html>';
        exit;
    }

    private function disable_feeds() {
        $feeds = array(
            'do_feed',
            'do_feed_rdf',
            'do_feed_rss',
            'do_feed_rss2',
            'do_feed_atom',
            'do_feed_rss2_comments',
            'do_feed_atom_comments'
        );

        foreach ( $feeds as $feed ) {
            add_action( $feed, array( $this, 'feed_die_message' ), 1 );
        }
    }

    public function feed_die_message() {
        $settings = $this->get_settings();

        if ( empty( $settings['enabled'] ) ) {
            return;
        }

        wp_die(
            'Le site est en maintenance. Les flux sont temporairement désactivés.',
            'Maintenance',
            array( 'response' => 503 )
        );
    }
}

new Onedev_Maintenance_Mode();