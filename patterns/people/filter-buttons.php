<?php
/**
 * Title: Filter Buttons
 * Slug: svlti/filter-buttons
 */
?>

<!-- wp:html -->
<div class="svlti-joinus-filter mb-12">
    <div class="grid grid-cols-3 gap-4 w-full" role="group" aria-label="Filter volunteer categories">

        <?php
        $categories = [
            ['label' => 'Teaching', 'value' => 'teaching'],
            ['label' => 'Mentoring', 'value' => 'mentoring'],
            ['label' => 'Admin', 'value' => 'admin'],
            ['label' => 'Fundraising', 'value' => 'fundraising'],
            ['label' => 'Events', 'value' => 'events'],
            ['label' => 'All Categories', 'value' => 'all'],
        ];
        foreach ($categories as $cat): ?>
            <button type="button"
                class="svlti-filter-pill wp-block-button__link wp-element-button filter-button w-full <?= $cat['value'] === 'all' ? 'is-active' : '' ?>"
                data-filter="<?= esc_attr($cat['value']) ?>" onclick="svltiJoinUsFilter(this)"
                aria-pressed="<?= $cat['value'] === 'all' ? 'true' : 'false' ?>">
                <?= esc_html($cat['label']) ?>
            </button>
        <?php endforeach; ?>

    </div>
</div>

<style>
    .svlti-filter-pill {
        transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        cursor: pointer;
    }

    .svlti-filter-pill.is-active {
        background-color: #2b8c77 !important;
        border-color: #2b8c77 !important;
        color: #ffffff !important;
        box-shadow: 0 2px 8px rgba(43, 140, 119, 0.25);
    }
</style>

<script>
    (function () {
        // Tracks active filters (array of slugs)
        var activeFilters = [];

        window.svltiJoinUsFilter = function (btn) {
            var value = btn.getAttribute('data-filter');
            var pills = document.querySelectorAll('.svlti-filter-pill');

            if (value === 'all') {
                // Deactivate all others, activate only "All"
                activeFilters = [];
                pills.forEach(function (p) {
                    var isAll = p.getAttribute('data-filter') === 'all';
                    p.classList.toggle('is-active', isAll);
                    p.setAttribute('aria-pressed', isAll ? 'true' : 'false');
                });
            } else {
                // Deactivate "All" pill
                var allPill = document.querySelector('.svlti-filter-pill[data-filter="all"]');
                if (allPill) {
                    allPill.classList.remove('is-active');
                    allPill.setAttribute('aria-pressed', 'false');
                }

                // Toggle this category
                var idx = activeFilters.indexOf(value);
                if (idx >= 0) {
                    activeFilters.splice(idx, 1);
                    btn.classList.remove('is-active');
                    btn.setAttribute('aria-pressed', 'false');
                } else {
                    activeFilters.push(value);
                    btn.classList.add('is-active');
                    btn.setAttribute('aria-pressed', 'true');
                }

                // If nothing active, revert to All
                if (activeFilters.length === 0) {
                    if (allPill) {
                        allPill.classList.add('is-active');
                        allPill.setAttribute('aria-pressed', 'true');
                    }
                }
            }

            svltiJoinUsApplyFilter();
        };

        function svltiJoinUsApplyFilter() {
            var cards = document.querySelectorAll('[data-joinus-card]');
            cards.forEach(function (card) {
                if (activeFilters.length === 0) {
                    // "All" — show everything
                    card.style.display = '';
                    return;
                }
                var cardCats = (card.getAttribute('data-categories') || '')
                    .split(',')
                    .map(function (s) { return s.trim().toLowerCase(); });

                // Show card if it matches ANY selected filter
                var match = activeFilters.some(function (f) {
                    return cardCats.includes(f);
                });
                card.style.display = match ? '' : 'none';
            });
        }
    })();
</script>
<!-- /wp:html -->