<?php
/**
 * Plugin Name: Onedev Mode Maintenance Simple
 * Description: Un plugin léger et avancé de mode maintenance : activation, texte, logo, couleurs, image de fond, réseaux sociaux et formulaire de contact AJAX.
 * Version: 1.5.1
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

        add_action( 'wp_ajax_nopriv_onedev_maintenance_contact', array( $this, 'handle_contact_form' ) );
        add_action( 'wp_ajax_onedev_maintenance_contact', array( $this, 'handle_contact_form' ) );

        $this->disable_feeds();
    }

    public function get_settings() {
        $defaults = array(
            'enabled'         => 1,
            'title'           => 'Nous mettons à jour le site',
            'message'         => 'Notre site est actuellement en mode maintenance pour vous offrir une meilleure expérience. Nous serons de retour dans très peu de temps.',
            'logo'            => '',
            'badge_text'      => 'En Mode Maintenance',
            'brand_color'     => '#111827',
            'bg_color'        => '#f3f4f6',
            'card_theme'      => 'light',
            'bg_image'        => '',
            'overlay_opacity' => 85,
            'custom_css'      => '',
            'email'           => '',
            'facebook'        => '',
            'instagram'       => '',
            'linkedin'        => '',
            'enable_form'     => 0,
        );

        $saved = get_option( $this->option_name, array() );
        return wp_parse_args( $saved, $defaults );
    }

    public function register_settings() {
        register_setting( 'onedev_maintenance_group', $this->option_name, array( $this, 'sanitize_settings' ) );
    }

    public function sanitize_settings( array $input ) {
        return array(
            'enabled'         => ! empty( $input['enabled'] ) ? 1 : 0,
            'title'           => isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '',
            'message'         => isset( $input['message'] ) ? sanitize_textarea_field( $input['message'] ) : '',
            'logo'            => isset( $input['logo'] ) ? esc_url_raw( $input['logo'] ) : '',
            'badge_text'      => isset( $input['badge_text'] ) ? sanitize_text_field( $input['badge_text'] ) : '',
            'brand_color'     => isset( $input['brand_color'] ) ? sanitize_hex_color( $input['brand_color'] ) : '#111827',
            'bg_color'        => isset( $input['bg_color'] ) ? sanitize_hex_color( $input['bg_color'] ) : '#f3f4f6',
            'card_theme'      => ( isset( $input['card_theme'] ) && in_array( $input['card_theme'], array( 'light', 'dark' ), true ) ) ? $input['card_theme'] : 'light',
            'bg_image'        => isset( $input['bg_image'] ) ? esc_url_raw( $input['bg_image'] ) : '',
            'overlay_opacity' => isset( $input['overlay_opacity'] ) ? absint( $input['overlay_opacity'] ) : 85,
            'custom_css'      => isset( $input['custom_css'] ) ? sanitize_textarea_field( $input['custom_css'] ) : '',
            'email'           => isset( $input['email'] ) ? sanitize_email( $input['email'] ) : '',
            'facebook'        => isset( $input['facebook'] ) ? esc_url_raw( $input['facebook'] ) : '',
            'instagram'       => isset( $input['instagram'] ) ? esc_url_raw( $input['instagram'] ) : '',
            'linkedin'        => isset( $input['linkedin'] ) ? esc_url_raw( $input['linkedin'] ) : '',
            'enable_form'     => ! empty( $input['enable_form'] ) ? 1 : 0,
        );
    }

    public function admin_menu() {
        add_options_page( 'Onedev Maintenance', 'Onedev Maintenance', 'manage_options', 'onedev-maintenance-mode', array( $this, 'settings_page' ) );
    }

    public function admin_assets( string $hook ) {
        if ( 'settings_page_onedev-maintenance-mode' !== $hook ) {
            return;
        }

        wp_enqueue_style( 'onedev-admin-css', plugins_url( 'assets/css/admin.css', __FILE__ ), array(), '1.0.0' );
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_media();

        wp_add_inline_script(
            'jquery-core',
            "jQuery(document).ready(function($){
                $('.onedev-color-picker').wpColorPicker();
                $('#onedev_overlay_opacity').on('input', function() { $('#onedev_opacity_val').text($(this).val() + '%'); });
                $('.onedev-upload-btn').on('click', function(e){
                    e.preventDefault();
                    let button = $(this); let targetInput = button.data('target'); let targetPreview = button.data('preview');
                    let mediaFrame = wp.media({ title: 'Sélectionner une image', button: { text: 'Utiliser' }, multiple: false });
                    mediaFrame.on('select', function(){
                        const attachment = mediaFrame.state().get('selection').first().toJSON();
                        $(targetInput).val(attachment.url);
                        $(targetPreview).html('<img src=\"' + attachment.url + '\" style=\"max-width:150px;height:auto;border-radius:8px;margin-top:10px;border:1px solid #ddd;\" />');
                    });
                    mediaFrame.open();
                });
                $('.onedev-remove-btn').on('click', function(e){
                    e.preventDefault(); $($(this).data('target')).val(''); $($(this).data('preview')).html('');
                });
            });"
        );
    }

    public function settings_page() {
        $settings = $this->get_settings();
        ?>
        <div class="wrap onedev-admin-wrap">
            <div class="onedev-header">
                <h1>⚙️ Onedev Maintenance Mode Pro</h1>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields( 'onedev_maintenance_group' ); ?>

                <div class="onedev-card">
                    <h2 class="onedev-card-header">1. Configuration Générale</h2>
                    <div class="onedev-card-body">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Statut du site</th>
                                <td>
                                    <div class="onedev-toggle-wrapper">
                                        <label class="onedev-toggle">
                                            <input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[enabled]" value="1" <?php checked( 1, $settings['enabled'] ); ?>>
                                            <span class="onedev-slider"></span>
                                        </label>
                                        <strong>Activer la page de maintenance</strong>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="onedev_title">Titre principal</label></th>
                                <td><input type="text" id="onedev_title" name="<?php echo esc_attr( $this->option_name ); ?>[title]" value="<?php echo esc_attr( $settings['title'] ); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="onedev_message">Message descriptif</label></th>
                                <td><textarea id="onedev_message" rows="4" name="<?php echo esc_attr( $this->option_name ); ?>[message]"><?php echo esc_textarea( $settings['message'] ); ?></textarea></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="onedev_badge_text">Texte du badge</label></th>
                                <td><input type="text" id="onedev_badge_text" name="<?php echo esc_attr( $this->option_name ); ?>[badge_text]" value="<?php echo esc_attr( $settings['badge_text'] ); ?>"></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="onedev-card">
                    <h2 class="onedev-card-header">2. Apparence & Design (Pro)</h2>
                    <div class="onedev-card-body">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="onedev_brand_color">Couleur de la marque</label></th>
                                <td>
                                    <input type="text" id="onedev_brand_color" class="onedev-color-picker" name="<?php echo esc_attr( $this->option_name ); ?>[brand_color]" value="<?php echo esc_attr( $settings['brand_color'] ); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="onedev_bg_color">Couleur d'arrière-plan</label></th>
                                <td>
                                    <input type="text" id="onedev_bg_color" class="onedev-color-picker" name="<?php echo esc_attr( $this->option_name ); ?>[bg_color]" value="<?php echo esc_attr( $settings['bg_color'] ); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="onedev_card_theme">Thème de la boîte (Carte)</label></th>
                                <td>
                                    <select id="onedev_card_theme" name="<?php echo esc_attr( $this->option_name ); ?>[card_theme]">
                                        <option value="light" <?php selected( 'light', $settings['card_theme'] ); ?>>☀️ Clair (Blanc)</option>
                                        <option value="dark" <?php selected( 'dark', $settings['card_theme'] ); ?>>🌙 Sombre (Noir)</option>
                                    </select>
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
                                    <div id="onedev-logo-preview"><?php if ( ! empty( $settings['logo'] ) ) : ?><img src="<?php echo esc_url( $settings['logo'] ); ?>" style="max-width:150px;border-radius:8px;margin-top:10px;border:1px solid #ddd;" /><?php endif; ?></div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Image de fond</th>
                                <td>
                                    <input type="hidden" id="onedev_bg_image" name="<?php echo esc_attr( $this->option_name ); ?>[bg_image]" value="<?php echo esc_url( $settings['bg_image'] ); ?>">
                                    <p>
                                        <button class="button button-secondary onedev-upload-btn" data-target="#onedev_bg_image" data-preview="#onedev-bg-preview">Choisir une image</button> 
                                        <button class="button onedev-remove-btn" data-target="#onedev_bg_image" data-preview="#onedev-bg-preview">Supprimer</button>
                                    </p>
                                    <div id="onedev-bg-preview"><?php if ( ! empty( $settings['bg_image'] ) ) : ?><img src="<?php echo esc_url( $settings['bg_image'] ); ?>" style="max-width:150px;border-radius:8px;margin-top:10px;border:1px solid #ddd;" /><?php endif; ?></div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="onedev_overlay_opacity">Opacité du filtre de fond</label></th>
                                <td>
                                    <input type="range" id="onedev_overlay_opacity" name="<?php echo esc_attr( $this->option_name ); ?>[overlay_opacity]" min="0" max="100" value="<?php echo esc_attr( $settings['overlay_opacity'] ); ?>" style="width: 200px; vertical-align: middle;">
                                    <span id="onedev_opacity_val" style="font-weight: 600; margin-left: 10px;"><?php echo esc_attr( $settings['overlay_opacity'] ); ?>%</span>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="onedev_custom_css">CSS Personnalisé</label></th>
                                <td>
                                    <textarea id="onedev_custom_css" name="<?php echo esc_attr( $this->option_name ); ?>[custom_css]" rows="4" style="font-family: monospace; background: #f0f0f1;"><?php echo esc_textarea( $settings['custom_css'] ); ?></textarea>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="onedev-card">
                    <h2 class="onedev-card-header">3. Contact & Réseaux Sociaux</h2>
                    <div class="onedev-card-body">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Formulaire de contact</th>
                                <td>
                                    <div class="onedev-toggle-wrapper">
                                        <label class="onedev-toggle">
                                            <input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[enable_form]" value="1" <?php checked( 1, $settings['enable_form'] ); ?>>
                                            <span class="onedev-slider"></span>
                                        </label>
                                        <strong>Afficher un formulaire de contact AJAX</strong>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="onedev_email">Adresse Email de réception</label></th>
                                <td><input type="email" id="onedev_email" name="<?php echo esc_attr( $this->option_name ); ?>[email]" value="<?php echo esc_attr( $settings['email'] ); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="onedev_facebook">Lien Facebook</label></th>
                                <td><input type="url" id="onedev_facebook" name="<?php echo esc_attr( $this->option_name ); ?>[facebook]" value="<?php echo esc_url( $settings['facebook'] ); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="onedev_instagram">Lien Instagram</label></th>
                                <td><input type="url" id="onedev_instagram" name="<?php echo esc_attr( $this->option_name ); ?>[instagram]" value="<?php echo esc_url( $settings['instagram'] ); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="onedev_linkedin">Lien LinkedIn</label></th>
                                <td><input type="url" id="onedev_linkedin" name="<?php echo esc_attr( $this->option_name ); ?>[linkedin]" value="<?php echo esc_url( $settings['linkedin'] ); ?>"></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="onedev-action-bar">
                    <?php submit_button( 'Enregistrer les modifications', 'primary', 'submit', false ); ?>
                    <a href="<?php echo esc_url( home_url( '?preview_onedev_maintenance=1' ) ); ?>" target="_blank" class="button button-secondary">
                        <span style="display:flex; align-items:center; gap:5px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            Prévisualiser la page
                        </span>
                    </a>
                </div>
            </form>
        </div>
        <?php
    }

    public function handle_contact_form() {
        if ( ! isset( $_POST['onedev_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['onedev_nonce'] ), 'onedev_contact_nonce' ) ) {
            wp_send_json_error( 'Session expirée. Veuillez recharger la page.' );
        }

        $name    = isset( $_POST['onedev_name'] ) ? sanitize_text_field( wp_unslash( $_POST['onedev_name'] ) ) : '';
        $email   = isset( $_POST['onedev_email'] ) ? sanitize_email( wp_unslash( $_POST['onedev_email'] ) ) : '';
        $message = isset( $_POST['onedev_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['onedev_message'] ) ) : '';

        if ( empty( $name ) || empty( $email ) || empty( $message ) ) {
            wp_send_json_error( 'Veuillez remplir tous les champs.' );
        }
        if ( ! is_email( $email ) ) {
            wp_send_json_error( 'Adresse email invalide.' );
        }

        $settings = $this->get_settings();
        $to       = ! empty( $settings['email'] ) ? $settings['email'] : get_option( 'admin_email' );
        $subject  = 'Nouveau message de contact - ' . get_bloginfo( 'name' );
        $body     = "Nom: $name\nEmail: $email\n\nMessage:\n$message";
        $headers  = array( 'Reply-To: ' . $name . ' <' . $email . '>' );

        if ( wp_mail( $to, $subject, $body, $headers ) ) {
            wp_send_json_success( 'Merci ! Votre message a été envoyé avec succès.' );
        } else {
            wp_send_json_error( "Désolé, une erreur s'est produite lors de l'envoi." );
        }
    }

    public function clear_caches() {
        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        if ( has_action( 'litespeed_purge_all' ) ) {
            do_action( 'litespeed_purge_all' );
        }
        // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

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
            echo '<div class="notice notice-warning is-dismissible" style="border-left-color: #f56e28;"><p><strong>⚠️ Onedev Maintenance Mode</strong> est actuellement <strong>ACTIF</strong>.</p></div>';
        }
    }

    public function render_maintenance_page() {
        $settings = $this->get_settings();
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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

        $css_url        = plugins_url( 'assets/css/maintenance.css', __FILE__ );
        $ajax_url       = admin_url( 'admin-ajax.php' );
        $clean_message  = wp_strip_all_tags( $settings['message'], true );
        
        $opacity_val    = intval( $settings['overlay_opacity'] ) / 100;
        $overlay_rgb    = ( $settings['card_theme'] === 'dark' ) ? '0, 0, 0' : '255, 255, 255';
        $dark_theme_css = ( $settings['card_theme'] === 'dark' ) ? ':root { --card-bg: #1f2937; --text-main: #f9fafb; --text-muted: #9ca3af; } .contact-form { background: #374151; border-color: #4b5563; } .contact-form input, .contact-form textarea { background: #1f2937; color: #fff; border-color: #4b5563; } .contact-form input:focus, .contact-form textarea:focus { box-shadow: 0 0 0 3px rgba(255,255,255,0.1); } .social-links a { background: #374151; }' : '';
        
        $contact_nonce  = wp_create_nonce( 'onedev_contact_nonce' );
        ?>
        <!DOCTYPE html>
        <html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, follow">
            <meta name="description" content="<?php echo esc_attr( wp_trim_words( $clean_message, 20 ) ); ?>">
            <title><?php echo esc_html( $settings['title'] ); ?> - <?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
            
            <?php
            // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
            echo '<link rel="stylesheet" href="' . esc_url( $css_url ) . '?v=1.5.1">';
            ?>
            <style>
                :root { 
                    --brand-color: <?php echo esc_attr( $settings['brand_color'] ); ?>; 
                    --primary-bg: <?php echo esc_attr( $settings['bg_color'] ); ?>;
                }
                .bg-overlay { background: rgba(<?php echo esc_attr( $overlay_rgb ); ?>, <?php echo esc_attr( $opacity_val ); ?>) !important; }
                
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo wp_strip_all_tags( $dark_theme_css ); 
                ?>
                
                <?php 
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo wp_strip_all_tags( $settings['custom_css'] ); 
                ?>
            </style>
        </head>
        <body>
            <?php if ( ! empty( $settings['bg_image'] ) ) : ?>
                <div class="bg-image" style="background-image: url('<?php echo esc_url( $settings['bg_image'] ); ?>');"></div>
                <div class="bg-overlay"></div>
            <?php endif; ?>
            
            <main class="box">
                <?php if ( ! empty( $settings['logo'] ) ) : ?>
                    <div class="logo-container"><img src="<?php echo esc_url( $settings['logo'] ); ?>" alt="Logo" /></div>
                <?php endif; ?>
                
                <h1><?php echo esc_html( $settings['title'] ); ?></h1>
                <p><?php echo nl2br( esc_html( $settings['message'] ) ); ?></p>
                <div class="badge"><?php echo esc_html( $settings['badge_text'] ); ?></div>
                
                <?php if ( ! empty( $settings['enable_form'] ) ) : ?>
                    <div class="contact-form">
                        <form id="onedev-contact-form">
                            <input type="text" name="onedev_name" placeholder="Votre nom" required>
                            <input type="email" name="onedev_email" placeholder="Votre adresse email" required>
                            <textarea name="onedev_message" placeholder="Votre message..." rows="3" required></textarea>
                            <input type="hidden" name="action" value="onedev_maintenance_contact">
                            <input type="hidden" name="onedev_nonce" value="<?php echo esc_attr( $contact_nonce ); ?>">
                            <button type="submit" id="onedev-submit-btn" class="submit-btn">Envoyer le message</button>
                            <div id="onedev-form-msg" class="form-message"></div>
                        </form>
                    </div>
                <?php endif; ?>
                
                <?php if ( ! empty( $settings['email'] ) || ! empty( $settings['facebook'] ) || ! empty( $settings['instagram'] ) || ! empty( $settings['linkedin'] ) ) : ?>
                    <div class="footer-extras">
                        <?php if ( ! empty( $settings['email'] ) ) : ?>
                            <div class="contact-email">Nous contacter : <a href="mailto:<?php echo esc_attr( $settings['email'] ); ?>"><?php echo esc_html( $settings['email'] ); ?></a></div>
                        <?php endif; ?>
                        
                        <?php if ( ! empty( $settings['facebook'] ) || ! empty( $settings['instagram'] ) || ! empty( $settings['linkedin'] ) ) : ?>
                            <div class="social-links">
                                <?php if ( ! empty( $settings['facebook'] ) ) : ?>
                                    <a href="<?php echo esc_url( $settings['facebook'] ); ?>" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg></a>
                                <?php endif; ?>
                                <?php if ( ! empty( $settings['instagram'] ) ) : ?>
                                    <a href="<?php echo esc_url( $settings['instagram'] ); ?>" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg></a>
                                <?php endif; ?>
                                <?php if ( ! empty( $settings['linkedin'] ) ) : ?>
                                    <a href="<?php echo esc_url( $settings['linkedin'] ); ?>" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path><rect x="2" y="9" width="4" height="12"></rect><circle cx="4" cy="4" r="2"></circle></svg></a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </main>
            
            <?php if ( ! empty( $settings['enable_form'] ) ) : ?>
                <script>
                document.getElementById("onedev-contact-form").addEventListener("submit", function(e) {
                    e.preventDefault();
                    var btn = document.getElementById("onedev-submit-btn");
                    var msgBox = document.getElementById("onedev-form-msg");
                    var formData = new FormData(this);

                    btn.disabled = true; btn.innerText = "Envoi en cours..."; msgBox.className = "form-message"; msgBox.innerText = "";

                    fetch("<?php echo esc_url( $ajax_url ); ?>", { method: "POST", body: formData })
                    .then(response => response.json())
                    .then(data => {
                        btn.disabled = false; btn.innerText = "Envoyer le message";
                        if(data.success) { msgBox.classList.add("success"); msgBox.innerText = data.data; this.reset(); }
                        else { msgBox.classList.add("error"); msgBox.innerText = data.data || "Une erreur est survenue."; }
                    })
                    .catch(error => {
                        btn.disabled = false; btn.innerText = "Envoyer le message"; msgBox.classList.add("error"); msgBox.innerText = "Erreur de connexion serveur.";
                    });
                });
                </script>
            <?php endif; ?>
        </body>
        </html>
        <?php
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
        if ( empty( $settings['enabled'] ) ) {
            return;
        }
        wp_die( 'Le site est en maintenance.', 'Maintenance', array( 'response' => 503 ) );
    }
}

new Onedev_Maintenance_Mode();