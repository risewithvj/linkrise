<?php
/**
 * Developer: Vijaya Kumar L
 * GitHub: https://github.com/risewithvj
 * LinkedIn: https://in.linkedin.com/in/vijayakumarl
 * Report Issues: https://github.com/risewithvj/linkrise/issues
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="wrap lr-admin"><h1>Settings</h1>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
<input type="hidden" name="action" value="linkrise_save_settings" />
<?php wp_nonce_field( 'linkrise_settings' ); ?>
<p><label>Redirect Prefix <input type="text" name="linkrise_redirect_prefix" value="<?php echo esc_attr( get_option( 'linkrise_redirect_prefix', 'go' ) ); ?>"/></label></p>
<p><button class="button button-primary" type="submit">Save</button></p>
</form></div>
