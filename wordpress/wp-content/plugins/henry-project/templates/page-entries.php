<?php
/**
 * Template Name: Henry Project Entries
 */

if (!defined('ABSPATH')) {
    exit;
}

$view_type = isset($_GET['view']) && $_GET['view'] === 'ajax' ? 'ajax' : 'rest';
?>


<div class="henry-project-container">
    <?php HenryProject::show_current_role(); ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="h4 mb-0"><?php _e('Project Entries', 'henry-project'); ?></h2>
                <a href="<?php echo esc_url(add_query_arg('view', $view_type === 'ajax' ? 'rest' : 'ajax')); ?>"
                class="btn btn-outline-secondary btn-sm">
                    <?php printf(__('Switch to %s Version', 'henry-project'), $view_type === 'ajax' ? 'REST' : 'AJAX'); ?>
                </a>
            </div>

            <div class="card-body">
                <?php if (is_user_logged_in()): ?>
                    <form id="entry-form" class="henry-project-form">
                        <div class="mb-3">
                            <label for="entry-content" class="form-label">
                                <?php _e('New Entry', 'henry-project'); ?>
                            </label>
                            <input type="text"
                                class="form-control"
                                id="entry-content"
                                required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <?php _e('Add Entry', 'henry-project'); ?>
                        </button>
                    </form>

                    <div id="entries-container">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3 class="h5 mb-0"><?php _e('Recent Entries', 'henry-project'); ?></h3>
                            <button id="sort-toggle" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-sort-down"></i>
                                <?php _e('Toggle Sort', 'henry-project'); ?>
                            </button>
                        </div>
                        <div id="entries-list" class="mb-3"></div>
                        <div id="pagination" class="d-flex justify-content-center"></div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <?php
                        printf(
                            __('Please %s to view and add entries.', 'henry-project'),
                            sprintf(
                                '<a href="%s">%s</a>',
                                esc_url(wp_login_url(get_permalink())),
                                __('log in', 'henry-project')
                            )
                        );
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
