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
        <button type="button" class="custom-filter-btn px-4"
            onclick="this.nextElementSibling.classList.toggle('hidden')">
            Filter
        </button>
        <div
            class="hidden absolute right-0 z-50 mt-2 w-56 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
            <div class="py-1">
                <?php foreach ($buttons as $button):
                    $selected = false;
                    if ($button['url'] === '/blog') {
                        $path = parse_url($current_url, PHP_URL_PATH);
                        $selected = ($path === '/blog' || $path === '/blog/');
                    } else {
                        $selected = (strpos($current_url, $button['url']) !== false);
                    }
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