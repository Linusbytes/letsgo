<?php
/**
 * Title: Courses Grid (Pillar Filters + Cards)
 * Slug: svlti/courses-grid
 * Categories: featured, courses
 */


const SVLTI_COURSE_POST_TYPE = 'course';      
const SVLTI_PILLAR_TAXONOMY = 'course-pillar'; 

// Helpers
if (!function_exists('svlti_courses_build_url')) {
    function svlti_courses_build_url(array $overrides = []): string
    {
        $current = [];
        foreach ($_GET as $k => $v) {
            $current[$k] = is_array($v)
                ? array_map('sanitize_text_field', wp_unslash($v))
                : sanitize_text_field(wp_unslash($v));
        }

        $merged = array_merge($current, $overrides);

        // reset pagination if filter changes
        if (isset($overrides['pillar'])) {
            $merged['paged'] = 1;
        }

        return esc_url(add_query_arg($merged));
    }
}

if (!function_exists('svlti_get_courses_query')) {
    function svlti_get_courses_query(string $pillar_slug = 'all', int $per_page = 9)
    {
        $args = [
            'post_type' => SVLTI_COURSE_POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => max(1, (int) get_query_var('paged', 1)),
        ];

        if ($pillar_slug !== '' && $pillar_slug !== 'all') {
            $args['tax_query'] = [
                [
                    'taxonomy' => SVLTI_PILLAR_TAXONOMY,
                    'field' => 'slug',
                    'terms' => [$pillar_slug],
                ],
            ];
        }

        return new WP_Query($args);
    }
}

if (!function_exists('svlti_course_card_data')) {
    function svlti_course_card_data(int $post_id): array
    {
        // ACF fields
        $course_title = get_the_title($post_id);
        $cert = get_field('certification', $post_id);
        $acf_excerpt = get_field('excerpt', $post_id); // ACF field named 'excerpt'
        $prereq = get_field('prerequisites', $post_id);

        $dur_val = get_field('duration_pt_1', $post_id);
        $dur_unit = get_field('duration_pt_2', $post_id);
        $learning_mode = get_field('learning_mode', $post_id);
        $assessment = get_field('assessment_type', $post_id);

        // Course pillars
        $pillars = get_the_terms($post_id, SVLTI_PILLAR_TAXONOMY);
        $pillars_text = '';
        if (is_array($pillars) && !is_wp_error($pillars)) {
            $pillars_text = implode(', ', wp_list_pluck($pillars, 'name'));
        }

        return [
            'title' => $course_title,
            'pillars' => $pillars_text,
            'certification' => $cert,
            'excerpt' => $acf_excerpt,
            'prereq' => $prereq,
            'duration' => trim((string) $dur_val . ' ' . (string) $dur_unit),
            'learning_mode' => $learning_mode,
            'assessment' => $assessment,
            'permalink' => get_permalink($post_id),
            'has_thumb' => has_post_thumbnail($post_id),
            'thumb_html' => get_the_post_thumbnail($post_id, 'large', [
                'alt' => esc_attr($course_title),
                'class' => 'w-full h-auto object-cover',
            ]),
        ];
    }
}

// Current filter
$current_pillar = isset($_GET['pillar']) ? sanitize_title(wp_unslash($_GET['pillar'])) : 'all';

// Pill terms for pills
$pillar_terms = get_terms([
    'taxonomy' => SVLTI_PILLAR_TAXONOMY,
    'hide_empty' => false,
]);

// Courses query
$courses_q = svlti_get_courses_query($current_pillar, 9);
?>

<!-- wp:group {"className":"w-full py-8"} -->
<div class="wp-block-group w-full py-8">

    <!-- wp:html -->
    <div class="flex flex-wrap justify-between items-center mb-8">
        <h2 class="text-3xl md:text-4xl font-bold text-[#2b8c77]">Courses</h2>


        <div class="relative inline-block text-left relative-dropdown-container w-full md:w-auto mt-4 md:mt-0">
            <div class="flex justify-end">
                <button type="button" class="custom-filter-btn px-4"
                    onclick="this.nextElementSibling.classList.toggle('hidden')">
                    Filter
                </button>
                <div
                    class="hidden absolute right-0 z-50 mt-14 w-56 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                    <div class="py-1">
                        <a href="<?= svlti_courses_build_url(['pillar' => 'all']) ?>"
                            class=" block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 <?= ($current_pillar === 'all' ? 'bg-gray-100 font-bold text-[#2b8c77]' : '') ?>">
                            All
                        </a>

                        <?php if (!is_wp_error($pillar_terms) && !empty($pillar_terms)): ?>
                            <?php foreach ($pillar_terms as $term):
                                $active = ($current_pillar === $term->slug);
                                ?>

                                <a href="<?= svlti_courses_build_url(['pillar' => $term->slug]) ?>"
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
    <d iv class="wp-block-group grid grid-cols-1 md:grid-cols-3 gap-6">

        <! -- wp:html -->
            <?php if ($courses_q->have_posts()): ?>
                <?php while ($courses_q->have_posts()):
                    $courses_q->the_post();
                    $post_id = get_the_ID();
                    $c = svlti_course_card_data($post_id);
                    ?>
                    <d iv class="bg-white rounded-lg border border-gray-200 overflow-hidden flex flex-col h-full">

                        <fig ure class="wp-block-image size-large h-full overflow-hidden">
                            <?php if ($c['has_thumb']): ?>
                                <?= $c['thumb_html']; ?>
                            <?php else: ?>
                                <div class="w-full" style="height:220px; background:#f3f4f6;"></div>
                            <?php endif; ?>
                            </figure>

                            <d iv class="p-6 flex flex-col h-full">

                                <h3 class="text-xl font-bold text-gray-900 mb-3">
                                    <?= esc_html($c['title']) ?>
                                </h3>

                                <?php if ($c['pillars']): ?>
                                    <p class="text-sm text-gray-600 mb-3">
                                        <strong>Pillars:</strong> <span><?= esc_html($c['pillars']) ?></span>
                                    </p>
                                <?php endif; ?>

                                <?php if ($c['certification']): ?>

                                    <div class="flex items-start 
                  t         ext-sm text-gray-600 mb-3">
                                        <svg class="w-4 h-4 mt-0.5 mr-2 text-yellow-500 flex-shrink-0"
                                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                            <path fill="currentColor"
                                                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                        </svg>
                                        <span><?= esc_html($c['certification']) ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($c['excerpt']): ?>

                                    <div class="flex items-start text-sm text-gray-600 mb-4">

                                        <svg class="w-4 h-4 mt-0.5 mr-2 text-blue-500 flex-shrink-0"
                                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                        <span><?= esc_html($c['excerpt']) ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($c['prereq']): ?>
                                    <p class="text-sm text-gray-600 mb-5">
                                        <strong>Prerequisites:</strong> <?= esc_html($c['prereq']) ?>
                                    </p>
                                <?php endif; ?>

                                <div class="grid grid-cols-3 gap-2 text-xs text-gray-600 mb-5">

                                    <div class="flex flex-col items-center text-center">

                                        <svg class="w-5 h-5 mb-1 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span class="font-medium"><?= esc_html($c['duration'] ?: '—') ?></span>
                                    </div>


                                    <div class="flex flex-col items-center text-center">

                                        <svg class="w-5 h-5 mb-1 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                        </svg>
                                        <span class="font-medium"><?= esc_html($c['learning_mode'] ?: '—') ?></span>
                                    </div>


                                    <div class="flex flex-col items-center text-center">

                                        <svg class="w-5 h-5 mb-1 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2" />
                                        </svg>
                                        <span class="font-medium"><?= esc_html($c['assessment'] ?: '—') ?></span>
                                    </div>
                                </div>

                                <div class="mt-auto">
                                    <a href="<?= esc_url($c['permalink']) ?>"
                                        class="block text-center bg-[#2b8c77] text-white text-sm font-medium rounded-md py-2 hover:bg-[#247565] transition-colors">
                                        Enroll
                                    </a>
                                </div>

        </div>
        </div>
    <?php endwhile;
                wp_reset_postdata(); ?>
<?php else: ?>
    <p class="text-sm opacity-70">No courses found.</p>
<?php endif; ?>
<!-- /wp:html -->

</div>
<!-- /wp:group -->

</div>
<!-- /wp:group -->