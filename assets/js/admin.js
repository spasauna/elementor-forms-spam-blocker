/**
 * Elementor Forms Spam Blocker - Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        initKeywordManagement();
    });

    function initKeywordManagement() {
        var $container = $('#efsb-keywords-container');
        var $list = $('#efsb-keywords-list');
        var $newKeywordInput = $('#efsb-new-keyword');
        var $addButton = $('#efsb-add-keyword');

        // Add new keyword
        $addButton.on('click', function() {
            addKeyword();
        });

        // Add keyword on Enter key
        $newKeywordInput.on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                addKeyword();
            }
        });

        // Remove keyword
        $container.on('click', '.efsb-remove-keyword', function() {
            var $item = $(this).closest('.efsb-keyword-item');
            
            if (confirm(efsbAdmin.confirmDelete)) {
                $item.css({
                    opacity: 0,
                    transform: 'translateX(-20px)',
                    transition: 'all 0.2s ease'
                });
                
                setTimeout(function() {
                    $item.remove();
                }, 200);
            }
        });

        function addKeyword() {
            var keyword = $newKeywordInput.val().trim();

            if (!keyword) {
                alert(efsbAdmin.emptyKeyword);
                $newKeywordInput.focus();
                return;
            }

            // Check for duplicates
            var exists = false;
            $list.find('.efsb-keyword-input').each(function() {
                if ($(this).val().toLowerCase() === keyword.toLowerCase()) {
                    exists = true;
                    return false;
                }
            });

            if (exists) {
                alert('This keyword already exists.');
                $newKeywordInput.val('').focus();
                return;
            }

            // Create new keyword item
            var $newItem = $('<div class="efsb-keyword-item">' +
                '<input type="text" name="efsb_options[keywords][]" value="' + escapeHtml(keyword) + '" class="regular-text efsb-keyword-input">' +
                '<button type="button" class="button efsb-remove-keyword" title="Remove">' +
                '<span class="dashicons dashicons-no-alt"></span>' +
                '</button>' +
                '</div>');

            $list.append($newItem);
            $newKeywordInput.val('').focus();
        }

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

})(jQuery);

