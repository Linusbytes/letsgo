<?php

/**
 * Title: Highlight Buttons
 * Slug: svlti/highlight-buttons
 */
?>

<!-- wp:html -->
<div class="flex justify-end mb-12">
    <?php
    $current_url = $_SERVER['REQUEST_URI'] ?? '';
    $buttons = [
        ['title' => 'All', 'url' => '/blog'],
        ['title' => 'Reflections', 'url' => '/blog/highlights/reflections'],
        ['title' => 'Student Projects', 'url' => '/blog/highlights/student-projects'],
        ['title' => 'Community Service', 'url' => '/blog/highlights/community-service'],
        ['title' => 'Learning Journeys', 'url' => '/blog/highlights/learning-journeys'],
    ];
    ?>

    <div class="relative inline-block text-left relative-dropdown-container">
        <button type="button" class="custom-filter-btn px-4" aria-expanded="false" aria-haspopup="true"
            onclick="const menu = this.nextElementSibling; const expanded = this.getAttribute('aria-expanded') === 'true'; this.setAttribute('aria-expanded', !expanded); menu.classList.toggle('hidden');">
            Filter
        </button>
        <div
            class="hidden absolute right-0 z-50 mt-2 w-56 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
            <div class="py-1">
                <?php foreach ($buttons as $button):
                    $path = parse_url($current_url, PHP_URL_PATH);
                    $selected = (rtrim($path, '/') === rtrim($button['url'], '/'));
                    ?>
                    <a href="<?= esc_url($button['url']) ?>"
                        class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 <?= $selected ? 'bg-gray-100 font-bold text-[#2b8c77]' : '' ?>">
                        <?= esc_html($button['title']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<!-- /wp:html -->