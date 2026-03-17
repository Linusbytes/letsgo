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
    function svlti_get_blogs_query(string $category_slug = 'all', int $per_page = 9)
    {
        $args = [
            'post_type' => SVLTI_BLOG_POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => max(1, (int) get_query_var('paged', 1)),
        ];

        if ($category_slug !== '' && $category_slug !== 'all') {
            $args['tax_query'] = [
                [
                    'taxonomy' => SVLTI_BLOG_CATEGORY,
                    'field' => 'slug',
                    'terms' => [$category_slug],
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
        $categories_text = '';
        if (!is_array($categories) || is_wp_error($categories)) {
            $categories = [];
        }



        return [
            'title' => $blog_title,
            'content' => $blog_content,
            'author_name' => $author_name,
            'date' => $post_date,
            'categories' => $categories,
            'permalink' => get_permalink($post_id),
            'has_thumb' => has_post_thumbnail($post_id),
            'thumb_html' => get_the_post_thumbnail($post_id, 'large', [
                'alt' => esc_attr($blog_title),
                'class' => 'w-full h-full object-cover',
            ]),
        ];
    }
}

// Current filter
$current_category = isset($_GET['category']) ? sanitize_title(wp_unslash($_GET['category'])) : 'all';

// Pill terms for pills
$category_terms = get_terms([
    'taxonomy' => SVLTI_BLOG_CATEGORY,
    'hide_empty' => false,
]);

// blogs query
$blogs_q = svlti_get_blogs_query($current_category, 9);
?>

<!-- wp:group {"className":"w-full py-8"} -->
<div class="wp-block-group w-full py-8">

    <!-- wp:html -->
    <div class="flex flex-wrap justify-between items-center mb-8">
        <h2 class="text-3xl md:text-4xl font-bold text-[#2b8c77]">Blog</h2>


        <div class="relative inline-block text-left relative-dropdown-container w-full md:w-auto mt-4 md:mt-0">
            <div class="flex justify-end">
                <button type="button" class="custom-filter-btn px-4" aria-expanded="false" aria-haspopup="true"
                    onclick="const menu = this.nextElementSibling; const expanded = this.getAttribute('aria-expanded') === 'true'; this.setAttribute('aria-expanded', !expanded); menu.classList.toggle('hidden');">
                    Filter
                </button>
                <div
                    class="hidden absolute right-0 z-50 mt-14 w-56 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                    <div class="py-1">
                        <a href="<?= svlti_blogs_build_url(['category' => 'all']) ?>"
                            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 <?= ($current_category === 'all' ? 'bg-gray-100 font-bold text-[#2b8c77]' : '') ?>">
                            All
                        </a>

                        <?php if (!is_wp_error($category_terms) && !empty($category_terms)): ?>
                            <?php foreach ($category_terms as $term):
                                $active = ($current_category === $term->slug);
                                ?>
                                <a href="<?= svlti_blogs_build_url(['category' => $term->slug]) ?>"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 <?= ($active ? 'bg-gray-100 font-bold text-[#2b8c77]' : '') ?>">
                                    <?= esc_html($term->name) ?>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
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