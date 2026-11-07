<?php
$filters = $args['filters'] ?? [];
$id = sanitize_html_class($args['id'] ?? 'pc-archive-filter-fields');
?>
<form class="pc-archive-filters" action="<?php echo esc_url($args['action']); ?>" method="get" data-archive-filters<?php echo function_exists('popcorn_core_cache_plugin_active') && popcorn_core_cache_plugin_active() ? ' data-archive-cache="plugin"' : ''; ?>>
  <?php foreach ($args['query_args'] ?? [] as $key => $value) : ?><?php if (is_scalar($value)) : ?><input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>"><?php endif; ?><?php endforeach; ?>
  <button type="button" class="pc-archive-filters__toggle" data-archive-toggle aria-expanded="false" aria-controls="<?php echo esc_attr($id); ?>"><?php echo popcorn_icon('filter'); ?><span>فیلترها</span><?php echo popcorn_icon('down'); ?></button>
  <div class="pc-archive-filters__fields" id="<?php echo esc_attr($id); ?>" data-archive-fields>
    <?php if ($args['search'] ?? true) : ?>
    <label class="pc-archive-filters__search"><span class="screen-reader-text"><?php echo esc_html($args['search_label'] ?? 'جست‌وجوی نام هنرمند'); ?></span><input type="search" name="<?php echo esc_attr($args['search_name'] ?? 'q'); ?>" value="<?php echo esc_attr($filters[$args['search_name'] ?? 'q'] ?? ''); ?>" placeholder="<?php echo esc_attr($args['search_placeholder'] ?? 'جست‌وجوی نام هنرمند...'); ?>" maxlength="100" autocomplete="off"><?php echo popcorn_icon('search'); ?></label>
    <?php endif; ?>
    <div class="pc-archive-filters__options">
      <?php foreach ($args['options'] as $key => $field) : ?>
        <label class="pc-archive-filters__select"><span class="screen-reader-text"><?php echo esc_html($field['label']); ?></span><select name="<?php echo esc_attr($key); ?>"><?php foreach ($field['values'] as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected($filters[$key] ?? '', $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select><?php echo popcorn_icon('down'); ?></label>
      <?php endforeach; ?>
      <button class="pc-archive-filters__submit" type="submit">اعمال فیلترها</button>
    </div>
  </div>
</form>
