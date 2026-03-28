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
    function svlti_get_courses_query(array $pillar_slugs = [], int $per_page = 9)
    {
        $args = [
            'post_type' => SVLTI_COURSE_POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => max(1, (int) get_query_var('paged', 1)),
        ];

        $filtered = array_filter($pillar_slugs, fn($s) => $s !== '' && $s !== 'all');

        if (!empty($filtered)) {
            $args['tax_query'] = [
                [
                    'taxonomy' => SVLTI_PILLAR_TAXONOMY,
                    'field' => 'slug',
                    'terms' => array_values($filtered),
                    'operator' => 'IN',
                ],
            ];
        }

        return new WP_Query($args);
    }
}

if (!function_exists('svlti_course_card_data')) {
    function svlti_course_card_data(int $post_id): array
    {
        $course_title = get_the_title($post_id);
        $cert = get_field('certification', $post_id);
        $acf_excerpt = get_field('excerpt', $post_id);
        $prereq = get_field('prerequisites', $post_id);

        $dur_val = get_field('duration_pt_1', $post_id);
        $dur_unit = get_field('duration_pt_2', $post_id);
        $learning_mode = get_field('learning_mode', $post_id);
        $assessment = get_field('assessment_type', $post_id);

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

// Current filter — now supports multiple pillars via pillar[] array
$raw_pillars = isset($_GET['pillar']) ? wp_unslash($_GET['pillar']) : [];
if (!is_array($raw_pillars)) {
    $raw_pillars = [$raw_pillars];
}
$current_pillars = array_map('sanitize_title', $raw_pillars);
$current_pillars = array_filter($current_pillars, fn($s) => $s !== '' && $s !== 'all');
$current_pillars = array_values($current_pillars);

// Pillar terms for filter
$pillar_terms = get_terms([
    'taxonomy' => SVLTI_PILLAR_TAXONOMY,
    'hide_empty' => false,
]);

// Courses query
$courses_q = svlti_get_courses_query($current_pillars, 9);

// Build label for the button
if (empty($current_pillars)) {
    $filter_label = 'All Pillars';
} elseif (count($current_pillars) === 1) {
    $matched = array_filter(
        is_array($pillar_terms) ? $pillar_terms : [],
        fn($t) => $t->slug === $current_pillars[0]
    );
    $filter_label = !empty($matched) ? reset($matched)->name : ucfirst($current_pillars[0]);
} else {
    $filter_label = count($current_pillars) . ' filters active';
}
?>

<!-- wp:group {"className":"w-full py-8"} -->
<div class="wp-block-group w-full py-8">

    <!-- wp:html -->
    <div class="flex flex-wrap justify-between items-center mb-8 w-full">
        <h2 class="text-3xl md:text-4xl font-bold text-[#2b8c77]">Courses</h2>

        <!-- Multi-select filter dropdown -->
        <div class="svlti-courses-filter-wrap" style="position:relative; display:inline-block;">
            <button type="button" class="custom-filter-btn px-4" aria-expanded="false" aria-haspopup="true"
                id="svlti-courses-filter-btn" onclick="svltiCoursesToggleFilter(this)">
                <?= esc_html($filter_label) ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                    style="width:14px;height:14px;margin-left:8px;transition:transform 0.2s;" aria-hidden="true">
                    <path fill-rule="evenodd"
                        d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                        clip-rule="evenodd" />
                </svg>
            </button>

            <form id="svlti-courses-filter-form" method="get" action="" class="svlti-filter-dropdown hidden" style="
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
                ">
                <!-- "All" resets selection -->
                <label class="svlti-filter-item"
                    style="display:flex;align-items:center;gap:10px;padding:9px 16px;cursor:pointer;font-size:0.875rem;color:#374151;transition:background 0.15s;">
                    <input type="checkbox" name="svlti_all_pillars" id="svlti-pillar-all" value="1"
                        onchange="svltiCoursesSelectAll(this)" <?= empty($current_pillars) ? 'checked' : '' ?>
                        style="accent-color:#2b8c77;width:15px;height:15px;cursor:pointer;">
                    <span
                        style="font-weight:<?= empty($current_pillars) ? '600' : '400' ?>;color:<?= empty($current_pillars) ? '#2b8c77' : 'inherit' ?>;">All
                        Pillars</span>
                </label>

                <div style="height:1px;background:#e5e7eb;margin:4px 0;"></div>

                <?php if (!is_wp_error($pillar_terms) && !empty($pillar_terms)): ?>
                    <?php foreach ($pillar_terms as $term):
                        $checked = in_array($term->slug, $current_pillars, true);
                        ?>
                        <label class="svlti-filter-item"
                            style="display:flex;align-items:center;gap:10px;padding:9px 16px;cursor:pointer;font-size:0.875rem;color:#374151;transition:background 0.15s;">
                            <input type="checkbox" name="pillar[]" value="<?= esc_attr($term->slug) ?>"
                                onchange="svltICoursesCatChange(this)" <?= $checked ? 'checked' : '' ?>
                                style="accent-color:#2b8c77;width:15px;height:15px;cursor:pointer;">
                            <span
                                style="font-weight:<?= $checked ? '600' : '400' ?>;color:<?= $checked ? '#2b8c77' : 'inherit' ?>;"><?= esc_html($term->name) ?></span>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div style="padding:8px 16px 4px;display:flex;gap:8px;">
                    <button type="submit"
                        style="flex:1;padding:8px 0;background:#2b8c77;color:#fff;border:none;border-radius:8px;font-size:0.8125rem;font-weight:600;cursor:pointer;transition:background 0.2s;"
                        onmouseover="this.style.background='#247565'"
                        onmouseout="this.style.background='#2b8c77'">Apply</button>
                    <button type="button" onclick="svltiCoursesClearFilter()"
                        style="flex:1;padding:8px 0;background:#f3f4f6;color:#374151;border:none;border-radius:8px;font-size:0.8125rem;font-weight:600;cursor:pointer;transition:background 0.2s;"
                        onmouseover="this.style.background='#e5e7eb'"
                        onmouseout="this.style.background='#f3f4f6'">Clear</button>
                </div>
            </form>
        </div>
    </div>
    <!-- /wp:html -->

    <!-- wp:group {"className":"grid grid-cols-1 md:grid-cols-3 gap-6"} -->
    <div class="wp-block-group grid grid-cols-1 md:grid-cols-3 gap-6">

        <!-- wp:html -->
        <?php if ($courses_q->have_posts()): ?>
            <?php while ($courses_q->have_posts()):
                $courses_q->the_post();
                $post_id = get_the_ID();
                $c = svlti_course_card_data($post_id);
                ?>
                <div class="bg-white rounded-lg border border-gray-200 overflow-hidden flex flex-col h-full">

                    <figure class="wp-block-image size-large h-full overflow-hidden">
                        <?php if ($c['has_thumb']): ?>
                            <?= $c['thumb_html']; ?>
                        <?php else: ?>
                            <div class="w-full" style="height:220px; background:#f3f4f6;"></div>
                        <?php endif; ?>
                    </figure>

                    <div class="p-6 flex flex-col h-full">

                        <h3 class="text-xl font-bold text-gray-900 mb-3">
                            <?= esc_html($c['title']) ?>
                        </h3>

                        <?php if ($c['pillars']): ?>
                            <p class="text-sm text-gray-600 mb-3">
                                <strong>Pillars:</strong> <span><?= esc_html($c['pillars']) ?></span>
                            </p>
                        <?php endif; ?>

                        <?php if ($c['certification']): ?>
                            <div class="flex items-start text-sm text-gray-600 mb-3">
                                <svg class="w-4 h-4 mt-0.5 mr-2 text-yellow-500 flex-shrink-0" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20">
                                    <path fill="currentColor"
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                                <span><?= esc_html($c['certification']) ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($c['excerpt']): ?>
                            <div class="flex items-start text-sm text-gray-600 mb-4">
                                <svg class="w-4 h-4 mt-0.5 mr-2 text-blue-500 flex-shrink-0" xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
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

<script>
    (function () {
        window.svltiCoursesToggleFilter = function (btn) {
            var form = document.getElementById('svlti-courses-filter-form');
            var expanded = btn.getAttribute('aria-expanded') === 'true';
            btn.setAttribute('aria-expanded', String(!expanded));
            form.classList.toggle('hidden');
            var svg = btn.querySelector('svg');
            if (svg) svg.style.transform = expanded ? '' : 'rotate(180deg)';
        };

        window.svltiCoursesSelectAll = function (allBox) {
            if (allBox.checked) {
                document.querySelectorAll('#svlti-courses-filter-form input[name="pillar[]"]')
                    .forEach(function (cb) { cb.checked = false; });
            }
        };

        window.svltICoursesCatChange = function (cb) {
            if (cb.checked) {
                var allBox = document.getElementById('svlti-pillar-all');
                if (allBox) allBox.checked = false;
            } else {
                var checked = document.querySelectorAll('#svlti-courses-filter-form input[name="pillar[]"]:checked');
                if (checked.length === 0) {
                    var allBox2 = document.getElementById('svlti-pillar-all');
                    if (allBox2) allBox2.checked = true;
                }
            }
        };

        window.svltiCoursesClearFilter = function () {
            var allBox = document.getElementById('svlti-pillar-all');
            if (allBox) allBox.checked = true;
            document.querySelectorAll('#svlti-courses-filter-form input[name="pillar[]"]')
                .forEach(function (cb) { cb.checked = false; });
            var url = new URL(window.location.href);
            url.searchParams.delete('pillar');
            url.searchParams.delete('pillar[]');
            url.searchParams.delete('paged');
            window.location.href = url.toString();
        };

        document.addEventListener('click', function (e) {
            var wrap = document.querySelector('.svlti-courses-filter-wrap');
            if (wrap && !wrap.contains(e.target)) {
                var form = document.getElementById('svlti-courses-filter-form');
                var btn = document.getElementById('svlti-courses-filter-btn');
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

        document.getElementById('svlti-courses-filter-form').addEventListener('submit', function () {
            var url = new URL(window.location.href);
            url.searchParams.delete('paged');
            this.action = url.pathname + (url.search ? url.search : '');
        });
    })();
</script>