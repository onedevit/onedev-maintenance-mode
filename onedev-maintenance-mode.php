<?php
/**
 * Plugin Name: Onedev Mode Maintenance Simple
 * Description: Un plugin léger et avancé de mode maintenance : activation, texte, logo, couleurs, image de fond et réseaux sociaux.
 * Version: 1.2.0
 * Author: onedev.ovh
 * Author URI: https://onedev.ovh
 * Requires PHP: 8.1
 * Requires at least: 6.6
 * Tested up to: 7.1
 * Text Domain: onedev-maintenance-mode
 * Plugin URI: https://onedev.ovh
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
            'bg_image'    => '',
            'brand_color' => '#111827',
            'email'       => '',
            'facebook'    => '',
            'instagram'   => '',
            'linkedin'    => '',
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
            'enabled'     => ! empty( $input['enabled'] ) ? 1 : 0,
            'title'       => isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '',
            'message'     => isset( $input['message'] ) ? sanitize_textarea_field( $input['message'] ) : '',
            'logo'        => isset( $input['logo'] ) ? esc_url_raw( $input['logo'] ) : '',
            'badge_text'  => isset( $input['badge_text'] ) ? sanitize_text_field( $input['badge_text'] ) : '',
            'bg_image'    => isset( $input['bg_image'] ) ? esc_url_raw( $input['bg_image'] ) : '',
            'brand_color' => isset( $input['brand_color'] ) ? sanitize_hex_color( $input['brand_color'] ) : '#111827',
            'email'       => isset( $input['email'] ) ? sanitize_email( $input['email'] ) : '',
            'facebook'    => isset( $input['facebook'] ) ? esc_url_raw( $input['facebook'] ) : '',
            'instagram'   => isset( $input['instagram'] ) ? esc_url_raw( $input['instagram'] ) : '',
            'linkedin'    => isset( $input['linkedin'] ) ? esc_url_raw( $input['linkedin'] ) : '',
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

        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_media();

        wp_add_inline_script(
            'jquery-core',
            "
            jQuery(document).ready(function($){
                $('.onedev-color-picker').wpColorPicker();

                $('.onedev-upload-btn').on('click', function(e){
                    e.preventDefault();
                    let button = $(this);
                    let targetInput = button.data('target');
                    let targetPreview = button.data('preview');

                    let mediaFrame = wp.media({
                        title: 'Sélectionner une image',
                        button: { text: 'Utiliser cette image' },
                        multiple: false
                    });

                    mediaFrame.on('select', function(){
                        const attachment = mediaFrame.state().get('selection').first().toJSON();
                        $(targetInput).val(attachment.url);
                        $(targetPreview).html('<img src=\"' + attachment.url + '\" style=\"max-width:180px;height:auto;border-radius:8px;margin-top:10px;border:1px solid #ddd;\" />');
                    });

                    mediaFrame.open();
                });

                $('.onedev-remove-btn').on('click', function(e){
                    e.preventDefault();
                    let targetInput = $(this).data('target');
                    let targetPreview = $(this).data('preview');
                    $(targetInput).val('');
                    $(targetPreview).html('');
                });
            });
            "
        );
    }

    public function settings_page() {
        $settings = $this->get_settings();
        ?>
        <div class="wrap">
            <h1>⚙️ Onedev Maintenance Mode Pro</h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'onedev_maintenance_group' ); ?>

                <table class="form-table">
                    <!-- SECTION: GÉNÉRAL -->
                    <tr><th colspan="2" style="padding-top:30px;"><h2 style="margin:0;">1. Configuration Générale</h2><hr></th></tr>
                    
                    <tr>
                        <th scope="row">Statut</th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[enabled]" value="1" <?php checked( 1, $settings['enabled'] ); ?>>
                                <strong>Activer la page de maintenance</strong>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="onedev_title">Titre principal</label></th>
                        <td>
                            <input type="text" id="onedev_title" class="regular-text" name="<?php echo esc_attr( $this->option_name ); ?>[title]" value="<?php echo esc_attr( $settings['title'] ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="onedev_message">Message descriptif</label></th>
                        <td>
                            <textarea id="onedev_message" class="large-text" rows="4" name="<?php echo esc_attr( $this->option_name ); ?>[message]"><?php echo esc_textarea( $settings['message'] ); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="onedev_badge_text">Texte du badge</label></th>
                        <td>
                            <input type="text" id="onedev_badge_text" class="regular-text" name="<?php echo esc_attr( $this->option_name ); ?>[badge_text]" value="<?php echo esc_attr( $settings['badge_text'] ); ?>">
                        </td>
                    </tr>

                    <!-- SECTION: DESIGN -->
                    <tr><th colspan="2" style="padding-top:30px;"><h2 style="margin:0;">2. Apparence & Design</h2><hr></th></tr>

                    <tr>
                        <th scope="row"><label for="onedev_brand_color">Couleur principale</label></th>
                        <td>
                            <input type="text" id="onedev_brand_color" class="onedev-color-picker" name="<?php echo esc_attr( $this->option_name ); ?>[brand_color]" value="<?php echo esc_attr( $settings['brand_color'] ); ?>">
                            <p class="description">Utilisée pour le badge et les effets de survol.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Logo du site</th>
                        <td>
                            <input type="hidden" id="onedev_logo" name="<?php echo esc_attr( $this->option_name ); ?>[logo]" value="<?php echo esc_url( $settings['logo'] ); ?>">
                            <p>
                                <button class="button button-secondary onedev-upload-btn" data-target="#onedev_logo" data-preview="#onedev-logo-preview">Choisir un logo</button>
                                <button class="button onedev-remove-btn" data-target="#onedev_logo" data-preview="#onedev-logo-preview">Supprimer</button>
                            </p>
                            <div id="onedev-logo-preview">
                                <?php if ( ! empty( $settings['logo'] ) ) : ?>
                                    <img src="<?php echo esc_url( $settings['logo'] ); ?>" style="max-width:180px;height:auto;border-radius:8px;margin-top:10px;" />
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Image de fond (Optionnel)</th>
                        <td>
                            <input type="hidden" id="onedev_bg_image" name="<?php echo esc_attr( $this->option_name ); ?>[bg_image]" value="<?php echo esc_url( $settings['bg_image'] ); ?>">
                            <p>
                                <button class="button button-secondary onedev-upload-btn" data-target="#onedev_bg_image" data-preview="#onedev-bg-preview">Choisir une image de fond</button>
                                <button class="button onedev-remove-btn" data-target="#onedev_bg_image" data-preview="#onedev-bg-preview">Supprimer</button>
                            </p>
                            <div id="onedev-bg-preview">
                                <?php if ( ! empty( $settings['bg_image'] ) ) : ?>
                                    <img src="<?php echo esc_url( $settings['bg_image'] ); ?>" style="max-width:180px;height:auto;border-radius:8px;margin-top:10px;border:1px solid #ddd;" />
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>

                    <!-- SECTION: CONTACT -->
                    <tr><th colspan="2" style="padding-top:30px;"><h2 style="margin:0;">3. Contact & Réseaux Sociaux</h2><hr></th></tr>

                    <tr>
                        <th scope="row"><label for="onedev_email">Adresse Email</label></th>
                        <td>
                            <input type="email" id="onedev_email" class="regular-text" name="<?php echo esc_attr( $this->option_name ); ?>[email]" value="<?php echo esc_attr( $settings['email'] ); ?>" placeholder="contact@votre-site.com">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="onedev_facebook">Lien Facebook</label></th>
                        <td>
                            <input type="url" id="onedev_facebook" class="regular-text" name="<?php echo esc_attr( $this->option_name ); ?>[facebook]" value="<?php echo esc_url( $settings['facebook'] ); ?>" placeholder="https://facebook.com/votre-page">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="onedev_instagram">Lien Instagram</label></th>
                        <td>
                            <input type="url" id="onedev_instagram" class="regular-text" name="<?php echo esc_attr( $this->option_name ); ?>[instagram]" value="<?php echo esc_url( $settings['instagram'] ); ?>" placeholder="https://instagram.com/votre-profil">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="onedev_linkedin">Lien LinkedIn</label></th>
                        <td>
                            <input type="url" id="onedev_linkedin" class="regular-text" name="<?php echo esc_attr( $this->option_name ); ?>[linkedin]" value="<?php echo esc_url( $settings['linkedin'] ); ?>" placeholder="https://linkedin.com/in/votre-profil">
                        </td>
                    </tr>
                </table>

                <p class="submit" style="display: flex; gap: 15px; align-items: center; margin-top: 30px;">
                    <?php submit_button( 'Enregistrer les modifications', 'primary', 'submit', false ); ?>
                    <a href="<?php echo esc_url( home_url( '?preview_onedev_maintenance=1' ) ); ?>" target="_blank" class="button button-secondary">👀 Prévisualiser la page</a>
                </p>
            </form>
        </div>
        <?php
    }

    public function clear_caches() {
        if ( has_action( 'litespeed_purge_all' ) ) do_action( 'litespeed_purge_all' );
        if ( function_exists( 'rocket_clean_domain' ) ) rocket_clean_domain();
        if ( function_exists( 'wp_cache_clear_cache' ) ) wp_cache_clear_cache();
        if ( function_exists( 'w3tc_pgcache_flush' ) ) w3tc_pgcache_flush();
    }

    public function admin_notice() {
        $settings = $this->get_settings();
        if ( current_user_can( 'manage_options' ) && ! empty( $settings['enabled'] ) ) {
            echo '<div class="notice notice-warning is-dismissible"><p><strong>⚠️ Onedev Maintenance Mode</strong> est actuellement <strong>ACTIF</strong>.</p></div>';
        }
    }

    public function render_maintenance_page() {
        $settings = $this->get_settings();
        
        $is_preview = isset( $_GET['preview_onedev_maintenance'] ) && current_user_can( 'manage_options' );

        if ( empty( $settings['enabled'] ) && ! $is_preview ) return;
        if ( current_user_can( 'manage_options' ) && ! $is_preview ) return;

        nocache_headers();
        header( 'HTTP/1.1 503 Service Temporarily Unavailable' );
        header( 'Status: 503 Service Temporarily Unavailable' );
        header( 'Retry-After: 3600' );

        $css_url = plugins_url( 'assets/css/maintenance.css', __FILE__ );
        $clean_message = strip_tags( $settings['message'] );
        $brand_color = esc_attr( $settings['brand_color'] );

        // بناء محتوى الشعار
        $logo_html = '';
        if ( ! empty( $settings['logo'] ) ) {
            $logo_html = '<div class="logo-container"><img src="' . esc_url( $settings['logo'] ) . '" alt="Logo" /></div>';
        }

        // بناء محتوى الخلفية
        $bg_html = '';
        if ( ! empty( $settings['bg_image'] ) ) {
            $bg_html = '
            <div class="bg-image" style="background-image: url(\'' . esc_url( $settings['bg_image'] ) . '\');"></div>
            <div class="bg-overlay"></div>';
        }

        // بناء محتوى التذييل (البريد والشبكات الاجتماعية)
        $footer_html = '';
        if ( ! empty( $settings['email'] ) || ! empty( $settings['facebook'] ) || ! empty( $settings['instagram'] ) || ! empty( $settings['linkedin'] ) ) {
            $footer_html .= '<div class="footer-extras">';
            
            if ( ! empty( $settings['email'] ) ) {
                $footer_html .= '<div class="contact-email">Nous contacter : <a href="mailto:' . esc_attr( $settings['email'] ) . '">' . esc_html( $settings['email'] ) . '</a></div>';
            }

            if ( ! empty( $settings['facebook'] ) || ! empty( $settings['instagram'] ) || ! empty( $settings['linkedin'] ) ) {
                $footer_html .= '<div class="social-links">';
                
                if ( ! empty( $settings['facebook'] ) ) {
                    $footer_html .= '<a href="' . esc_url( $settings['facebook'] ) . '" target="_blank" rel="noopener" title="Facebook"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg></a>';
                }
                if ( ! empty( $settings['instagram'] ) ) {
                    $footer_html .= '<a href="' . esc_url( $settings['instagram'] ) . '" target="_blank" rel="noopener" title="Instagram"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg></a>';
                }
                if ( ! empty( $settings['linkedin'] ) ) {
                    $footer_html .= '<a href="' . esc_url( $settings['linkedin'] ) . '" target="_blank" rel="noopener" title="LinkedIn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path><rect x="2" y="9" width="4" height="12"></rect><circle cx="4" cy="4" r="2"></circle></svg></a>';
                }

                $footer_html .= '</div>';
            }
            $footer_html .= '</div>';
        }

        echo '<!DOCTYPE html>
        <html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, follow">
            <meta name="description" content="' . esc_attr( wp_trim_words( $clean_message, 20 ) ) . '">
            <title>' . esc_html( $settings['title'] ) . ' - ' . esc_html( get_bloginfo( 'name' ) ) . '</title>
            <link rel="stylesheet" href="' . esc_url( $css_url ) . '?v=1.3.0">
            <style>
                :root { --brand-color: ' . $brand_color . '; }
            </style>
        </head>
        <body>
            ' . $bg_html . '
            <main class="box">
                ' . $logo_html . '
                <h1>' . esc_html( $settings['title'] ) . '</h1>
                <p>' . nl2br( esc_html( $settings['message'] ) ) . '</p>
                <div class="badge">' . esc_html( $settings['badge_text'] ) . '</div>
                ' . $footer_html . '
            </main>
        </body>
        </html>';
        exit;
    }

    private function disable_feeds() {
        $feeds = array( 'do_feed', 'do_feed_rdf', 'do_feed_rss', 'do_feed_rss2', 'do_feed_atom', 'do_feed_rss2_comments', 'do_feed_atom_comments' );
        foreach ( $feeds as $feed ) {
            add_action( $feed, array( $this, 'feed_die_message' ), 1 );
        }
    }

    public function feed_die_message() {
        $settings = $this->get_settings();
        if ( empty( $settings['enabled'] ) ) return;
        wp_die( 'Le site est en maintenance.', 'Maintenance', array( 'response' => 503 ) );
    }
}

new Onedev_Maintenance_Mode();