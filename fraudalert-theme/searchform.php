<?php
/**
 * Custom Search Form
 * Called by get_search_form()
 *
 * @package FraudAlert
 */
?>
<form role="search" method="get" class="search-form-wrap" action="<?php echo esc_url(home_url('/')); ?>">
  <label for="s-<?php echo uniqid(); ?>" class="sr-only"><?php _e('Search', 'fraudalert'); ?></label>
  <input
    type="search"
    id="s-<?php echo uniqid(); ?>"
    class="search-input"
    placeholder="Search alerts, guides, scam types..."
    value="<?php echo esc_attr(get_search_query()); ?>"
    name="s"
    autocomplete="off"
  >
  <button type="submit" class="search-submit" aria-label="Search">🔍</button>
</form>
