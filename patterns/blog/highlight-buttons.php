<?php

/**
 * Title: Highlight Buttons
 * Slug: svlti/highlight-buttons
 */
?>

<!-- wp:html -->
<div class="flex justify-end mb-12">
    <?php
    $current_path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');

    $buttons = [
        ['title' => 'Reflections',       'url' => '/blog/highlights/reflections'],
        ['title' => 'Student Projects',  'url' => '/blog/highlights/student-projects'],
        ['title' => 'Community Service', 'url' => '/blog/highlights/community-service'],
        ['title' => 'Learning Journeys', 'url' => '/blog/highlights/learning-journeys'],
    ];

    // Determine which items are currently selected (multi-select via ?highlight[]=slug)
    $raw_hl = isset($_GET['highlight']) ? wp_unslash($_GET['highlight']) : [];
    if (!is_array($raw_hl)) {
        $raw_hl = [$raw_hl];
    }
    $selected_slugs = array_map('sanitize_title', array_filter($raw_hl));

    // Build label
    if (empty($selected_slugs)) {
        $hl_label = 'All';
    } elseif (count($selected_slugs) === 1) {
        $match = array_filter($buttons, fn($b) => sanitize_title($b['title']) === $selected_slugs[0]);
        $hl_label = !empty($match) ? reset($match)['title'] : ucfirst($selected_slugs[0]);
    } else {
        $hl_label = count($selected_slugs) . ' filters active';
    }
    ?>

    <div class="svlti-hl-filter-wrap" style="position:relative; display:inline-block;">
        <button
            type="button"
            class="custom-filter-btn px-4"
            aria-expanded="false"
            aria-haspopup="true"
            id="svlti-hl-filter-btn"
            onclick="svltiHlToggleFilter(this)"
        >
            <?= esc_html($hl_label) ?>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;margin-left:8px;transition:transform 0.2s;" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
        </button>

        <form
            id="svlti-hl-filter-form"
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
            <!-- All resets selection -->
            <label class="svlti-filter-item" style="display:flex;align-items:center;gap:10px;padding:9px 16px;cursor:pointer;font-size:0.875rem;color:#374151;transition:background 0.15s;">
                <input
                    type="checkbox"
                    name="svlti_hl_all"
                    id="svlti-hl-all"
                    value="1"
                    onchange="svltiHlSelectAll(this)"
                    <?= empty($selected_slugs) ? 'checked' : '' ?>
                    style="accent-color:#2b8c77;width:15px;height:15px;cursor:pointer;"
                >
                <span style="font-weight:<?= empty($selected_slugs) ? '600' : '400' ?>;color:<?= empty($selected_slugs) ? '#2b8c77' : 'inherit' ?>;">All</span>
            </label>

            <div style="height:1px;background:#e5e7eb;margin:4px 0;"></div>

            <?php foreach ($buttons as $button):
                $slug = sanitize_title($button['title']);
                $checked = in_array($slug, $selected_slugs, true);
                ?>
                <label class="svlti-filter-item" style="display:flex;align-items:center;gap:10px;padding:9px 16px;cursor:pointer;font-size:0.875rem;color:#374151;transition:background 0.15s;">
                    <input
                        type="checkbox"
                        name="highlight[]"
                        value="<?= esc_attr($slug) ?>"
                        onchange="svltiHlCatChange(this)"
                        <?= $checked ? 'checked' : '' ?>
                        style="accent-color:#2b8c77;width:15px;height:15px;cursor:pointer;"
                    >
                    <span style="font-weight:<?= $checked ? '600' : '400' ?>;color:<?= $checked ? '#2b8c77' : 'inherit' ?>;"><?= esc_html($button['title']) ?></span>
                </label>
            <?php endforeach; ?>

            <div style="padding:8px 16px 4px;display:flex;gap:8px;">
                <button
                    type="submit"
                    style="flex:1;padding:8px 0;background:#2b8c77;color:#fff;border:none;border-radius:8px;font-size:0.8125rem;font-weight:600;cursor:pointer;transition:background 0.2s;"
                    onmouseover="this.style.background='#247565'" onmouseout="this.style.background='#2b8c77'"
                >Apply</button>
                <button
                    type="button"
                    onclick="svltiHlClearFilter()"
                    style="flex:1;padding:8px 0;background:#f3f4f6;color:#374151;border:none;border-radius:8px;font-size:0.8125rem;font-weight:600;cursor:pointer;transition:background 0.2s;"
                    onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'"
                >Clear</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    window.svltiHlToggleFilter = function (btn) {
        var form = document.getElementById('svlti-hl-filter-form');
        var expanded = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', String(!expanded));
        form.classList.toggle('hidden');
        var svg = btn.querySelector('svg');
        if (svg) svg.style.transform = expanded ? '' : 'rotate(180deg)';
    };

    window.svltiHlSelectAll = function (allBox) {
        if (allBox.checked) {
            document.querySelectorAll('#svlti-hl-filter-form input[name="highlight[]"]')
                .forEach(function (cb) { cb.checked = false; });
        }
    };

    window.svltiHlCatChange = function (cb) {
        if (cb.checked) {
            var allBox = document.getElementById('svlti-hl-all');
            if (allBox) allBox.checked = false;
        } else {
            var checked = document.querySelectorAll('#svlti-hl-filter-form input[name="highlight[]"]:checked');
            if (checked.length === 0) {
                var allBox2 = document.getElementById('svlti-hl-all');
                if (allBox2) allBox2.checked = true;
            }
        }
    };

    window.svltiHlClearFilter = function () {
        var allBox = document.getElementById('svlti-hl-all');
        if (allBox) allBox.checked = true;
        document.querySelectorAll('#svlti-hl-filter-form input[name="highlight[]"]')
            .forEach(function (cb) { cb.checked = false; });
        var url = new URL(window.location.href);
        url.searchParams.delete('highlight');
        url.searchParams.delete('highlight[]');
        window.location.href = url.toString();
    };

    document.addEventListener('click', function (e) {
        var wrap = document.querySelector('.svlti-hl-filter-wrap');
        if (wrap && !wrap.contains(e.target)) {
            var form = document.getElementById('svlti-hl-filter-form');
            var btn  = document.getElementById('svlti-hl-filter-btn');
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
})();
</script>
<!-- /wp:html -->