<?php if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * @var int $id
 */

?>
<button class="preview button button-primary"
        type="button"
        id="<?php echo brizy()->get_slug(); ?>-admin-enable"
><?php _e( 'Edit with Brizy', 'brizy' ); ?></button>
<button class="preview button button-primary"
        type="button"
        id="<?php echo brizy()->get_slug(); ?>-admin-disable"
><?php _e( 'Back to WordPress', 'brizy' ); ?></button>