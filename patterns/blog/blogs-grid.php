<?php

/**
 * Title: Blogs Listing
 * Slug: svlti/blogs-grid
 */

const SVLTI_BLOG_POST_TYPE = 'blogs';
const SVLTI_BLOG_CATEGORY = 'blog-category';

// Helpers
if (!function_exists('svlti_blogs_build_url')) {
    function svlti_blogs_build_url(array $overrides = []): string
    {
        $current = [];
        foreach ($_GET as $k => $v) {
            $current[$k] = is_array($v)
                ? array_map('sanitize_text_field', wp_unslash($v))
                : sanitize_text_field(wp_unslash($v));
        }

        $merged = array_merge($current, $overrides);

        // reset pagination if filter changes
        if (isset($overrides['category'])) {
            $merged['paged'] = 1;
        }

        return esc_url(add_query_arg($merged));
    }
}

if (!function_exists('svlti_get_blogs_query')) {
    function svlti_get_blogs_query(array $category_slugs = [], int $per_page = 9)
    {
        $args = [
            'post_type'      => SVLTI_BLOG_POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => max(1, (int) get_query_var('paged', 1)),
        ];

        $filtered = array_filter($category_slugs, fn($s) => $s !== '' && $s !== 'all');

        if (!empty($filtered)) {
            $args['tax_query'] = [
                [
                    'taxonomy' => SVLTI_BLOG_CATEGORY,
                    'field'    => 'slug',
                    'terms'    => array_values($filtered),
                    'operator' => 'IN',
                ],
            ];
        }

        return new WP_Query($args);
    }
}

if (!function_exists('svlti_blog_card_data')) {
    function svlti_blog_card_data(int $post_id): array
    {
        // ACF fields
        $blog_title = get_the_title($post_id);
        $blog_content = get_post_field('post_content', $post_id);

        // data for author
        // $author_id = get_post_field('post_author', $post_id);
        // $author_name = get_the_author_meta('display_name', $author_id);
        $author_name = get_post_field('author', $post_id);

        $post_date = get_the_date('M j, Y', $post_id);


        // blog categories
        $categories = get_the_terms($post_id, SVLTI_BLOG_CATEGORY);
        if (!is_array($categories) || is_wp_error($categories)) {
            $categories = [];
        }

        return [
            'title'      => $blog_title,
            'content'    => $blog_content,
            'author_name'=> $author_name,
            'date'       => $post_date,
            'categories' => $categories,
            'permalink'  => get_permalink($post_id),
            'has_thumb'  => has_post_thumbnail($post_id),
            'thumb_html' => get_the_post_thumbnail($post_id, 'large', [
                'alt'   => esc_attr($blog_title),
                'class' => 'w-full h-full object-cover',
            ]),
        ];
    }
}

// Current filter — now supports multiple categories via category[] array
$raw_cats = isset($_GET['category']) ? wp_unslash($_GET['category']) : [];
if (!is_array($raw_cats)) {
    $raw_cats = [$raw_cats];
}
$current_categories = array_map('sanitize_title', $raw_cats);
// Treat 'all' or empty as no filter
$current_categories = array_filter($current_categories, fn($s) => $s !== '' && $s !== 'all');
$current_categories = array_values($current_categories);

// Pill terms for filter
$category_terms = get_terms([
    'taxonomy'   => SVLTI_BLOG_CATEGORY,
    'hide_empty' => false,
]);

// blogs query
$blogs_q = svlti_get_blogs_query($current_categories, 9);

// Build label for the button
if (empty($current_categories)) {
    $filter_label = 'All Categories';
} elseif (count($current_categories) === 1) {
    // Find the term name
    $matched = array_filter(
        is_array($category_terms) ? $category_terms : [],
        fn($t) => $t->slug === $current_categories[0]
    );
    $filter_label = !empty($matched) ? reset($matched)->name : ucfirst($current_categories[0]);
} else {
    $filter_label = count($current_categories) . ' filters active';
}
?>

<!-- wp:group {"className":"w-full py-8"} -->
<div class="wp-block-group w-full py-8">

    <!-- wp:html -->
    <div class="flex flex-wrap justify-between items-center mb-8">
        <h2 class="text-3xl md:text-4xl font-bold text-[#2b8c77]">Blog</h2>

        <!-- Multi-select filter dropdown -->
        <div class="svlti-filter-wrap" style="position:relative; display:inline-block;">
            <button
                type="button"
                class="custom-filter-btn px-4"
                aria-expanded="false"
                aria-haspopup="true"
                id="svlti-blogs-filter-btn"
                onclick="svltiBlogsToggleFilter(this)"
            >
                <?= esc_html($filter_label) ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;margin-left:8px;transition:transform 0.2s;" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                </svg>
            </button>

            <form
                id="svlti-blogs-filter-form"
                method="get"
                action=""
                class="svlti-filter-dropdown hidden"
                style="
                    position: absolute;
                    top: calc(100% + 8px);
                    right: 0;
                    z-index: 9999;
                    min-width: 220px;
                    background: #fff;
                    border-radius: 10px;
                    box-shadow: 0 8px 24px rgba(0,0,0,0.13);
                    border: 1px solid rgba(0,0,0,0.08);
                    padding: 8px 0;
                "
            >
                <!-- "All" resets selection -->
                <label class="svlti-filter-item" style="display:flex;align-items:center;gap:10px;padding:9px 16px;cursor:pointer;font-size:0.875rem;color:#374151;transition:background 0.15s;">
                    <input
                        type="checkbox"
                        name="svlti_all_cats"
                        id="svlti-cat-all"
                        value="1"
                        onchange="svltiBlogsSelectAll(this)"
                        <?= empty($current_categories) ? 'checked' : '' ?>
                        style="accent-color:#2b8c77;width:15px;height:15px;cursor:pointer;"
                    >
                    <span style="font-weight:<?= empty($current_categories) ? '600' : '400' ?>;color:<?= empty($current_categories) ? '#2b8c77' : 'inherit' ?>;">All Categories</span>
                </label>

                <div style="height:1px;background:#e5e7eb;margin:4px 0;"></div>

                <?php if (!is_wp_error($category_terms) && !empty($category_terms)): ?>
                    <?php foreach ($category_terms as $term):
                        $checked = in_array($term->slug, $current_categories, true);
                        ?>
                        <label class="svlti-filter-item" style="display:flex;align-items:center;gap:10px;padding:9px 16px;cursor:pointer;font-size:0.875rem;color:#374151;transition:background 0.15s;">
                            <input
                                type="checkbox"
                                name="category[]"
                                value="<?= esc_attr($term->slug) ?>"
                                onchange="svltiBlogsCatChange(this)"
                                <?= $checked ? 'checked' : '' ?>
                                style="accent-color:#2b8c77;width:15px;height:15px;cursor:pointer;"
                            >
                            <span style="font-weight:<?= $checked ? '600' : '400' ?>;color:<?= $checked ? '#2b8c77' : 'inherit' ?>;"><?= esc_html($term->name) ?></span>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div style="padding:8px 16px 4px;display:flex;gap:8px;">
                    <button
                        type="submit"
                        style="flex:1;padding:8px 0;background:#2b8c77;color:#fff;border:none;border-radius:8px;font-size:0.8125rem;font-weight:600;cursor:pointer;transition:background 0.2s;"
                        onmouseover="this.style.background='#247565'" onmouseout="this.style.background='#2b8c77'"
                    >Apply</button>
                    <button
                        type="button"
                        onclick="svltiBlogsClearFilter()"
                        style="flex:1;padding:8px 0;background:#f3f4f6;color:#374151;border:none;border-radius:8px;font-size:0.8125rem;font-weight:600;cursor:pointer;transition:background 0.2s;"
                        onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'"
                    >Clear</button>
                </div>
            </form>
        </div>
    </div>
    <!-- /wp:html -->

    <!-- wp:group {"className":"grid grid-cols-1 md:grid-cols-3 gap-6"} -->
    <div class="wp-block-group grid grid-cols-1 md:grid-cols-3 gap-6">

        <!-- wp:html -->
        <?php if ($blogs_q->have_posts()): ?>
            <?php while ($blogs_q->have_posts()):
                $blogs_q->the_post();
                $post_id = get_the_ID();
                $c = svlti_blog_card_data($post_id);
                ?>
                <div class="bg-white rounded-lg border border-gray-200 overflow-hidden flex flex-col h-full">

                    <figure class="wp-block-image size-large h-full overflow-hidden">
                        <?php if ($c['has_thumb']): ?>
                            <?= $c['thumb_html']; ?>
                        <?php else: ?>
                            <div class="w-full h-full bg-gray-100"></div>
                        <?php endif; ?>
                    </figure>

                    <div class="p-6 flex flex-col">

                        <h3 class="text-xl font-bold text-gray-900 mb-3">
                            <?= esc_html($c['title']) ?>
                        </h3>

                        <?php if (!empty($c['categories'])): ?>
                            <div class="flex flex-wrap gap-2 mb-3">
                                <?php foreach ($c['categories'] as $term): ?>
                                    <span
                                        class="inline-flex items-center px-3 py-1 rounded-full text-xs text-dark-green font-medium border border-dark-green">
                                        <?= esc_html($term->name) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="mt-10">
                            <div class="flex items-center gap-2 mb-4 text-xs font-bold tracking-widest uppercase">
                                <span class="text-[#2b8c77]">
                                    <?= esc_html($c['author_name']) ?>
                                </span>
                                <span class="text-gray-300">|</span>
                                <span class="text-gray-500">
                                    <?= esc_html($c['date']) ?>
                                </span>
                            </div>
                            <a href="<?= esc_url($c['permalink']) ?>"
                                class="block text-center bg-[#2b8c77] text-white text-sm font-medium rounded-md py-2 hover:bg-[#247565] transition-colors">
                                Read ->
                            </a>
                        </div>

                    </div>
                </div>
            <?php endwhile;
            wp_reset_postdata(); ?>
        <?php else: ?>
            <p class="text-sm opacity-70">No blogs found.</p>
        <?php endif; ?>
        <!-- /wp:html -->

    </div>
    <!-- /wp:group -->

</div>
<!-- /wp:group -->

<script>
(function () {
    // Toggle the dropdown open/close
    window.svltiBlogsToggleFilter = function (btn) {
        var form = document.getElementById('svlti-blogs-filter-form');
        var expanded = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', String(!expanded));
        form.classList.toggle('hidden');
        // rotate chevron
        var svg = btn.querySelector('svg');
        if (svg) svg.style.transform = expanded ? '' : 'rotate(180deg)';
    };

    // Clicking "All Categories" unchecks everything else
    window.svltiBlogsSelectAll = function (allBox) {
        if (allBox.checked) {
            var catBoxes = document.querySelectorAll('#svlti-blogs-filter-form input[name="category[]"]');
            catBoxes.forEach(function (cb) { cb.checked = false; });
        }
    };

    // Checking any category unchecks "All"
    window.svltiBlogsCatChange = function (cb) {
        if (cb.checked) {
            var allBox = document.getElementById('svlti-cat-all');
            if (allBox) allBox.checked = false;
        } else {
            // if nothing checked, re-check All
            var catBoxes = document.querySelectorAll('#svlti-blogs-filter-form input[name="category[]"]:checked');
            if (catBoxes.length === 0) {
                var allBox2 = document.getElementById('svlti-cat-all');
                if (allBox2) allBox2.checked = true;
            }
        }
    };

    // Clear all — recheck "All" and navigate
    window.svltiBlogsClearFilter = function () {
        var allBox = document.getElementById('svlti-cat-all');
        if (allBox) allBox.checked = true;
        var catBoxes = document.querySelectorAll('#svlti-blogs-filter-form input[name="category[]"]');
        catBoxes.forEach(function (cb) { cb.checked = false; });
        // Navigate to current page without category param
        var url = new URL(window.location.href);
        url.searchParams.delete('category');
        url.searchParams.delete('category[]');
        url.searchParams.delete('paged');
        window.location.href = url.toString();
    };

    // Close dropdown when clicking outside
    document.addEventListener('click', function (e) {
        var wrap = document.querySelector('.svlti-filter-wrap');
        if (wrap && !wrap.contains(e.target)) {
            var form = document.getElementById('svlti-blogs-filter-form');
            var btn  = document.getElementById('svlti-blogs-filter-btn');
            if (form && !form.classList.contains('hidden')) {
                form.classList.add('hidden');
                if (btn) {
                    btn.setAttribute('aria-expanded', 'false');
                    var svg = btn.querySelector('svg');
                    if (svg) svg.style.transform = '';
                }
            }
        }
    });

    // Reset paged when submitting
    document.getElementById('svlti-blogs-filter-form').addEventListener('submit', function () {
        // remove old paged param from action
        var url = new URL(window.location.href);
        url.searchParams.delete('paged');
        this.action = url.pathname + (url.search ? url.search : '');
    });
})();
</script>